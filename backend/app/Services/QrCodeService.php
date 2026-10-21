<?php

namespace App\Services;

use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

class QrCodeService
{
    /** SVG markup of a QR code for $data (plain, no logo). */
    public function svg(string $data, int $size = 240): string
    {
        return (new SvgWriter)->write(new QrCode(data: $data, errorCorrectionLevel: ErrorCorrectionLevel::Medium, size: $size, margin: 8))->getString();
    }

    /**
     * PNG bytes: error correction High, white quiet zone, brand logo (config/qr.php) centred on a white rounded backing.
     * A missing / unreadable logo file yields the plain code (still valid).
     */
    public function png(string $data): string
    {
        $size = (int) config('qr.size', 600);
        $margin = (int) config('qr.margin', 36);
        // endroid adds the margin around `size`, so shrink it to keep the final edge at exactly config('qr.size')
        $png = (new PngWriter)->write(new QrCode(data: $data, errorCorrectionLevel: ErrorCorrectionLevel::High, size: $size - 2 * $margin, margin: $margin))->getString();

        return $this->withLogo($png, $size);
    }

    private function withLogo(string $png, int $size): string
    {
        $path = (string) config('qr.logo_path');
        $logoRaw = $path !== '' && is_file($path) ? @file_get_contents($path) : false;
        $logo = $logoRaw ? @imagecreatefromstring($logoRaw) : false;
        $qr = @imagecreatefromstring($png);
        if (! $logo || ! $qr) {
            return $png;
        }

        $w = imagesx($qr);
        $logoW = (int) round($w * min(0.22, max(0.05, (float) config('qr.logo_ratio', 0.20))));
        $logoH = (int) round($logoW * imagesy($logo) / imagesx($logo));
        $pad = (int) round($logoW * 0.08);
        $boxW = $logoW + 2 * $pad;
        $boxH = $logoH + 2 * $pad;
        $x = intdiv($w - $boxW, 2);
        $y = intdiv(imagesy($qr) - $boxH, 2);

        imagealphablending($qr, true);
        $white = imagecolorallocate($qr, 255, 255, 255);
        $this->roundedRect($qr, $x, $y, $boxW, $boxH, (int) round($boxW * 0.18), $white);
        imagecopyresampled($qr, $logo, $x + $pad, $y + $pad, 0, 0, $logoW, $logoH, imagesx($logo), imagesy($logo));

        ob_start();
        imagepng($qr, null, 9);

        return (string) ob_get_clean();
    }

    private function roundedRect(\GdImage $im, int $x, int $y, int $w, int $h, int $r, int $color): void
    {
        imagefilledrectangle($im, $x + $r, $y, $x + $w - $r - 1, $y + $h - 1, $color);
        imagefilledrectangle($im, $x, $y + $r, $x + $w - 1, $y + $h - $r - 1, $color);
        foreach ([[$x + $r, $y + $r], [$x + $w - $r - 1, $y + $r], [$x + $r, $y + $h - $r - 1], [$x + $w - $r - 1, $y + $h - $r - 1]] as [$cx, $cy]) {
            imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $color);
        }
    }
}
