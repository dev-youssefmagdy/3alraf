<?php

namespace App\Services\Whatsapp\Chat;

/** Private-disk layout for new files: whatsapp-media/{yyyy}/{mm}/ (older rows keep their stored paths under the legacy per-session folder). */
class MediaPaths
{
    public static function dir(): string
    {
        return 'whatsapp-media/'.now()->format('Y/m');
    }
}
