<?php

namespace App\Services\Casting;

/** The configured image-to-video profile (workflow template + required models + the settings verified by a real clip). */
final class VideoProfile
{
    public static function current(): array
    {
        $name = (string) config('casting.video.profile');
        $p = config('casting.video.profiles.'.$name) ?? [];

        return ['key' => $name] + $p + ['models' => [], 'lengths' => [33], 'fps' => 24, 'steps' => 20, 'sizes' => [], 'verified' => false, 'workflow' => '', 'negative' => ''];
    }

    /** [w, h] for an ad format, from the profile's verified sizes. */
    public static function size(string $format): array
    {
        $s = self::current()['sizes'];

        return $s[$format] ?? reset($s) ?: [480, 832];
    }
}
