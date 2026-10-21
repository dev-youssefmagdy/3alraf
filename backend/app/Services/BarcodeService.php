<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorSVG;

class BarcodeService
{
    /** In-house EAN-13 prefix (GS1 "200-299" = restricted/internal circulation). */
    public const PREFIX = '200';

    public static function checkDigit(string $first12): int
    {
        $sum = 0;
        foreach (str_split($first12) as $i => $d) {
            $sum += (int) $d * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - $sum % 10) % 10;
    }

    public static function isValidEan13(string $code): bool
    {
        return preg_match('/^\d{13}$/', $code) === 1 && self::checkDigit(substr($code, 0, 12)) === (int) $code[12];
    }

    /** Random EAN-13 not present in products.barcode / product_variants.barcode (incl. soft-deleted). */
    public function generateUnique(): string
    {
        do {
            $first = self::PREFIX.str_pad((string) random_int(0, 999_999_999), 9, '0', STR_PAD_LEFT);
            $code = $first.self::checkDigit($first);
        } while (
            Product::withTrashed()->where('barcode', $code)->exists()
            || ProductVariant::withTrashed()->where('barcode', $code)->exists()
        );

        return $code;
    }

    /** Code128 render of the EAN-13 digits. */
    public function svg(string $code, int $height = 60): string
    {
        return (new BarcodeGeneratorSVG)->getBarcode($code, BarcodeGeneratorSVG::TYPE_CODE_128, 2, $height);
    }

    public function png(string $code, int $height = 60): string
    {
        return (new BarcodeGeneratorPNG)->getBarcode($code, BarcodeGeneratorPNG::TYPE_CODE_128, 2, $height);
    }
}
