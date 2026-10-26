<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

/** Platform rules for the AI Studio (docs/plans/ai-features.md §2 Studio): caption limit, hashtag range, aspect ratios. */
enum SocialPlatform: string
{
    use HasLabels;

    case TIKTOK = 'tiktok';
    case INSTAGRAM = 'instagram';
    case FACEBOOK = 'facebook';
    case X = 'x';
    case PINTEREST = 'pinterest';
    case WHATSAPP = 'whatsapp';

    public function label(): string
    {
        return __('ai.platforms.'.$this->value);
    }

    /** Max characters of the caption (hashtags included). */
    public function charLimit(): int
    {
        return match ($this) {
            self::TIKTOK => 2200, self::INSTAGRAM => 2200, self::FACEBOOK => 3000, self::X => 280, self::PINTEREST => 500, self::WHATSAPP => 1024,
        };
    }

    public function maxHashtags(): int
    {
        return match ($this) {
            self::TIKTOK => 5, self::INSTAGRAM => 15, self::FACEBOOK => 3, self::X => 2, self::PINTEREST => 8, self::WHATSAPP => 0,
        };
    }

    /** @return array<int,string> allowed aspect ratios, first = preferred */
    public function aspectRatios(): array
    {
        return match ($this) {
            self::TIKTOK => ['9:16'], self::INSTAGRAM => ['4:5', '1:1', '9:16'], self::FACEBOOK => ['1:1', '4:5'], self::X => ['16:9', '1:1'],
            self::PINTEREST => ['2:3', '9:16'], self::WHATSAPP => ['1:1', '9:16'],
        };
    }

    public function aspect(): string
    {
        return $this->aspectRatios()[0];
    }

    /** Provider image size for the preferred ratio (OpenAI-style sizes). */
    public function imageSize(): string
    {
        return match ($this->aspect()) {
            '1:1' => '1024x1024', '16:9' => '1536x1024', default => '1024x1536',
        };
    }

    public function supportsVideo(): bool
    {
        return in_array($this, [self::TIKTOK, self::INSTAGRAM, self::FACEBOOK, self::X, self::WHATSAPP], true);
    }

    public function rules(): array
    {
        return ['chars' => $this->charLimit(), 'hashtags' => $this->maxHashtags(), 'aspects' => $this->aspectRatios(), 'aspect' => $this->aspect(), 'video' => $this->supportsVideo()];
    }
}
