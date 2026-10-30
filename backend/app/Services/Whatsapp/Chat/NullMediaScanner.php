<?php

namespace App\Services\Whatsapp\Chat;

class NullMediaScanner implements MediaScanner
{
    public function isClean(string $absolutePath): bool
    {
        return true;
    }
}
