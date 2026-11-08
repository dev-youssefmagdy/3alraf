<?php

namespace App\Http\Requests\Marketer;

use App\Enums\Marketing\SuggestionPriority;
use App\Http\Requests\Admin\BaseRequest;
use App\Models\MarketingSuggestion;
use App\Services\Marketing\Suggestions\SuggestionTypeRegistry;
use App\Support\Marketing\UnknownFields;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Panel edit form of a suggestion (perm ai_suggestions.edit on the route). Rules = the type handler's payload schema (same as the API) + common
 * fields. Form encodings are decoded first from the handler's editFields(): `json` (textarea JSON list), `list` (one per line), `id-list`
 * (comma separated), `bool` (kept only when true or already present), empty values dropped. The engine normalises / sanitises afterwards.
 */
class SuggestionEditRequest extends BaseRequest
{
    private ?MarketingSuggestion $s = null;

    private array $jsonErrors = [];

    public function suggestion(): MarketingSuggestion
    {
        return $this->s ??= MarketingSuggestion::query()->findOrFail((int) $this->route('id'));
    }

    protected function prepareForValidation(): void
    {
        $s = $this->suggestion();
        $fields = app(SuggestionTypeRegistry::class)->get($s->type)->editFields($s->target_entity_type);
        $payload = (array) $this->input('payload', []);
        $original = (array) $s->payload;
        foreach ($fields as $f) {
            $path = substr($f['name'], strlen('payload.'));
            $v = Arr::get($payload, $path);
            switch ($f['kind']) {
                case 'json':
                    if (is_string($v) && trim($v) !== '') {
                        $d = json_decode($v, true);
                        is_array($d) ? Arr::set($payload, $path, $d) : $this->jsonErrors[$f['name']] = __('marketing.suggestions.errors.bad_json');
                    } else {
                        Arr::forget($payload, $path);
                    }
                    break;
                case 'list':
                    Arr::set($payload, $path, is_string($v) ? array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $v)), fn ($x) => $x !== '')) : (array) $v);
                    break;
                case 'id-list':
                    Arr::set($payload, $path, is_string($v) ? array_values(array_filter(array_map('trim', explode(',', $v)), fn ($x) => $x !== '')) : (array) $v);
                    break;
                case 'bool':
                    $on = filter_var($v, FILTER_VALIDATE_BOOL);
                    ($on || Arr::has($original, $path)) ? Arr::set($payload, $path, $on) : Arr::forget($payload, $path);
                    break;
            }
        }
        $this->merge(['payload' => self::prune($payload)]);
    }

    /** Remove '', null and [] recursively (false booleans are kept). */
    private static function prune(array $a): array
    {
        foreach ($a as $k => $v) {
            if (is_array($v)) {
                $v = self::prune($v);
            }
            if ($v === '' || $v === null || $v === []) {
                unset($a[$k]);
            } else {
                $a[$k] = $v;
            }
        }

        return array_is_list($a) ? $a : $a;
    }

    public function rules(): array
    {
        $s = $this->suggestion();
        $payload = app(SuggestionTypeRegistry::class)->get($s->type)->rules($s->target_entity_type);
        $payload['payload'] = ['required', 'array'];

        return [
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'reasoning' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(SuggestionPriority::values())],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'note' => ['nullable', 'string', 'max:500'],
        ] + $payload;
    }

    public function withValidator(Validator $v): void
    {
        $v->after(function (Validator $v) {
            foreach ($this->jsonErrors as $k => $m) {
                $v->errors()->add($k, $m);
            }
            foreach (UnknownFields::find(['payload' => $this->input('payload', [])], array_keys($this->rules())) as $k) {
                $v->errors()->add($k, __('marketing.suggestions.errors.unknown_field', ['field' => $k]));
            }
        });
    }

    /** Values for SuggestionEditData (expires_at always "sent" from the form). */
    public function editData(): array
    {
        $v = $this->validated();

        return ['title' => $v['title'], 'description' => $v['description'] ?? '', 'reasoning' => $v['reasoning'] ?? '', 'priority' => $v['priority'],
            'payload' => $this->input('payload', []), 'expires_at' => ! empty($v['expires_at']) ? \Carbon\CarbonImmutable::parse($v['expires_at'])->format('Y-m-d H:i:s') : null,
            'note' => $v['note'] ?? null];
    }
}
