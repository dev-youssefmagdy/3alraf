<?php

namespace App\Services;

use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

class QrCodeService
{
    /** SVG markup of a QR code for $data. */
    public function svg(string $data, int $size = 240): string
    {
        return (new SvgWriter)->write(new QrCode(data: $data, errorCorrectionLevel: ErrorCorrectionLevel::Medium, size: $size, margin: 8))->getString();
    }
}
