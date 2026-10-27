<?php

namespace App\Services\Growth;

use App\Models\Interaction;
use App\Models\TenantProduct;
use Illuminate\Support\Carbon;

/**
 * Interactions inbox (plan N4). Text is PII-masked BEFORE it is stored or sent to any AI; the raw text is never persisted. Classification comes from n8n/AI
 * (validated against fixed sets) or, when absent, from the rule-based fallback here. Escalation (complaint, safety, refund, conflict, missing data) is always human.
 */
class InteractionService
{
    public const CHANNELS = ['whatsapp', 'facebook', 'instagram', 'review', 'site_search', 'email'];
    public const CATEGORIES = ['price', 'shipping', 'availability', 'trust', 'photos', 'size_color', 'returns', 'usage', 'safety'];
    public const INTENTS = ['purchase_intent', 'question', 'complaint', 'praise', 'other'];
    public const URGENCY = ['low', 'normal', 'high'];
    public const SENTIMENT = ['negative', 'neutral', 'positive'];
    public const MESSAGING = ['whatsapp', 'facebook', 'instagram']; // 24h customer-service window

    private const KEYWORDS = [
        'safety' => ['allerg', 'burn', 'rash', 'toxic', 'unsafe', 'danger', 'حساسية', 'حروق', 'تسمم', 'خطر', 'التهاب'],
        'returns' => ['refund', 'return', 'exchange', 'استرجاع', 'ارجاع', 'إرجاع', 'استبدال', 'فلوسي'],
        'shipping' => ['shipping', 'delivery', 'deliver', 'tracking', 'courier', 'شحن', 'توصيل', 'الشحنة', 'اوردر', 'أوردر'],
        'price' => ['price', 'cost', 'how much', 'discount', 'offer', 'سعر', 'بكام', 'كام', 'خصم', 'عرض', 'غالي'],
        'availability' => ['available', 'in stock', 'out of stock', 'متاح', 'متوفر', 'موجود', 'نفذ'],
        'trust' => ['original', 'authentic', 'fake', 'scam', 'trust', 'اصلي', 'أصلي', 'تقليد', 'نصب', 'مضمون'],
        'photos' => ['photo', 'picture', 'image', 'صورة', 'صور'],
        'size_color' => ['size', 'color', 'colour', 'shade', 'مقاس', 'لون', 'حجم'],
        'usage' => ['how to use', 'how do i', 'usage', 'ingredients', 'استخدام', 'طريقة', 'ازاي', 'إزاي', 'مكونات'],
    ];
    private const BUY = ['want to buy', 'order', 'i want', 'interested', 'عايز', 'عاوز', 'اطلب', 'أطلب', 'اشتري', 'حابب'];
    private const NEG = ['bad', 'terrible', 'worst', 'angry', 'disappointed', 'broken', 'damaged', 'late', 'سيء', 'وحش', 'زعلان', 'مش راضي', 'تالف', 'اتأخر', 'متأخر', 'نصب'];
    private const POS = ['love', 'great', 'excellent', 'thanks', 'perfect', 'تحفة', 'ممتاز', 'شكرا', 'شكراً', 'رائع', 'حلو'];
    private const OPT_OUT = ['stop', 'unsubscribe', 'opt out', 'الغاء الاشتراك', 'إلغاء الاشتراك', 'وقف الرسائل', 'بطلوا'];

    public function __construct(private ActionLogger $log) {}

    /** @return array{0:Interaction,1:bool} [interaction, created] - idempotent per (channel, external_id). */
    public function ingest(array $d, string $source = 'n8n'): array
    {
        $existing = Interaction::where('channel', $d['channel'])->where('external_id', $d['external_id'])->first();
        $cls = $this->classification($d);
        if ($existing) {
            $existing->update(array_filter($cls, fn ($v) => $v !== null) + ($existing->status === 'new' && array_filter($cls) ? ['status' => 'classified'] : []));

            return [$existing->fresh(), false];
        }
        $productId = ! empty($d['product_id']) && TenantProduct::where('product_id', $d['product_id'])->exists() ? (int) $d['product_id'] : null;
        $masked = mb_substr(PiiScrubber::text(trim((string) $d['text'])), 0, 2000);
        $received = ! empty($d['received_at']) ? Carbon::parse($d['received_at']) : now();
        $fallback = $this->fallback($masked);
        $provided = array_filter($cls, fn ($v) => $v !== null);
        $final = $provided + $fallback;
        $optOut = (bool) ($d['opted_out'] ?? false) || $this->contains(mb_strtolower($masked), self::OPT_OUT);
        $i = Interaction::create([
            'channel' => $d['channel'], 'external_id' => $d['external_id'], 'thread_id' => $d['thread_id'] ?? null, 'product_id' => $productId, 'text_masked' => $masked,
            'text_hash' => sha1($this->normalise($masked)), 'language' => $d['language'] ?? (preg_match('/\p{Arabic}/u', $masked) ? 'ar' : 'en'), 'consent' => (bool) ($d['consent'] ?? false),
            'opted_out' => $optOut, 'received_at' => $received, 'window_expires_at' => in_array($d['channel'], self::MESSAGING, true) ? $received->copy()->addHours(24) : null,
            'status' => 'classified',
        ] + $final);
        $reason = $this->escalationReason($i);
        if ($reason) {
            $i->update(['status' => 'escalated', 'escalation_reason' => $reason]);
        }
        $this->log->log('interaction.ingested', $source === 'n8n' ? 'n8n' : 'admin', null, null, $i, 'ok', ['channel' => $i->channel, 'category' => $i->category]);

        return [$i->fresh(), true];
    }

    private function classification(array $d): array
    {
        $pick = fn (string $k, array $set) => in_array($d[$k] ?? null, $set, true) ? $d[$k] : null;

        return ['category' => $pick('category', self::CATEGORIES), 'intent' => $pick('intent', self::INTENTS), 'urgency' => $pick('urgency', self::URGENCY), 'sentiment' => $pick('sentiment', self::SENTIMENT)];
    }

    /** Rule-based fallback classifier (EN + Egyptian Arabic keywords). */
    public function fallback(string $text): array
    {
        $t = mb_strtolower($text);
        $cat = null;
        foreach (self::KEYWORDS as $c => $words) {
            if ($this->contains($t, $words)) {
                $cat = $c;
                break;
            }
        }
        $neg = $this->contains($t, self::NEG) || in_array($cat, ['safety'], true);
        $sent = $neg ? 'negative' : ($this->contains($t, self::POS) ? 'positive' : 'neutral');
        $intent = $this->contains($t, self::BUY) ? 'purchase_intent' : ($neg || $cat === 'returns' ? 'complaint' : ($sent === 'positive' ? 'praise' : (str_contains($t, '?') || str_contains($t, '؟') || $cat ? 'question' : 'other')));

        return ['category' => $cat, 'intent' => $intent, 'urgency' => ($cat === 'safety' || $neg) ? 'high' : 'normal', 'sentiment' => $sent];
    }

    public function escalationReason(Interaction $i): ?string
    {
        $t = mb_strtolower($i->text_masked);
        if ($i->category === 'safety' || $this->contains($t, self::KEYWORDS['safety'])) {
            return 'safety';
        }
        if ($i->category === 'returns' || $this->contains($t, ['refund', 'chargeback', 'استرجاع', 'فلوسي'])) {
            return 'refund';
        }
        if ($i->intent === 'complaint' || $i->sentiment === 'negative') {
            return 'complaint';
        }
        if ($this->contains($t, ['lawyer', 'sue', 'legal', 'محامي', 'قضية', 'بلاغ'])) {
            return 'conflict';
        }

        return null;
    }

    public function markResponded(int $id, ?int $adminId = null, string $source = 'admin'): Interaction
    {
        $i = Interaction::findOrFail($id);
        $i->update(['first_response_at' => $i->first_response_at ?? now(), 'status' => $i->status === 'escalated' ? 'escalated' : 'replied']);
        $this->log->log('interaction.responded', $source, $adminId, null, $i);

        return $i;
    }

    public function close(int $id, ?int $adminId): Interaction
    {
        $i = Interaction::findOrFail($id);
        $i->update(['status' => 'closed']);
        $this->log->log('interaction.closed', 'admin', $adminId, null, $i);

        return $i;
    }

    public function windowOpen(Interaction $i): ?bool
    {
        return $i->window_expires_at ? $i->window_expires_at->isFuture() : null;
    }

    public function replyLevel(?\App\Models\Tenant $t = null): int
    {
        $t ??= current_tenant();

        return max(1, min(4, (int) (((($t?->settings ?? [])['growth'] ?? [])['reply_level'] ?? 1))));
    }

    public function setReplyLevel(int $level, ?int $adminId): void
    {
        $t = current_tenant();
        $s = $t->settings ?? [];
        $s['growth']['reply_level'] = max(1, min(4, $level));
        $t->update(['settings' => $s]);
        $this->log->log('interaction.level', 'admin', $adminId, null, null, 'ok', ['level' => $s['growth']['reply_level']]);
    }

    public function unanswered(int $slaHours = 24)
    {
        return Interaction::whereNull('first_response_at')->where('opted_out', false)->whereIn('status', ['new', 'classified', 'escalated'])->where('received_at', '<', now()->subHours($slaHours));
    }

    private function contains(string $t, array $words): bool
    {
        foreach ($words as $w) {
            if (str_contains($t, $w)) {
                return true;
            }
        }

        return false;
    }

    private function normalise(string $t): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]/u', '', $t))));
    }
}
