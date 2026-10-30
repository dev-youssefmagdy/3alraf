<?php

namespace App\Services\Whatsapp\Chat;

/** Private-disk layout: whatsapp-media/{owner}/{yyyy}/{mm}/ (owner = "central" or "tenant-N"). */
class MediaPaths
{
    public static function dir(int $ownerKey): string
    {
        return 'whatsapp-media/'.($ownerKey ? "tenant-$ownerKey" : 'central').'/'.now()->format('Y/m');
    }
}
