<?php

namespace App\Services\Whatsapp\Chat;

/** Hook point for a virus scanner on outbound uploads. Bind another implementation to enable one. */
interface MediaScanner
{
    /** @return bool true when the file is clean */
    public function isClean(string $absolutePath): bool;
}
