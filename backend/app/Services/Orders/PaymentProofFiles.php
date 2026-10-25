<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Enums\ProofStatus;
use App\Models\Order;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Hardening + storage of payment-proof files (checkout and re-upload share it): real MIME sniff (never the client name/type),
 * images must decode and stay within the dimension cap, sha256 for reuse detection, random file name, private disk only.
 */
class PaymentProofFiles
{
    public const DISK = 'private';

    private const EXT = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];

    /** @return array{hash:string,ext:string,mime:string} */
    public function inspect(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if (! $file->isValid() || ! $path || ! is_file($path)) {
            $this->fail(__('orders.proof_invalid'));
        }
        if ($file->getSize() > (int) config('checkout.proof.max_kb') * 1024) {
            $this->fail(__('orders.proof_too_large', ['kb' => config('checkout.proof.max_kb')]));
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
        if (! in_array($mime, config('checkout.proof.mimes'), true)) {
            $this->fail(__('orders.proof_invalid'));
        }
        if ($mime === 'application/pdf') {
            if (! str_starts_with((string) file_get_contents($path, false, null, 0, 1024), '%PDF-')) {
                $this->fail(__('orders.proof_invalid'));
            }
        } else {
            $info = @getimagesize($path);
            $max = (int) config('checkout.proof.max_dimension');
            if (! $info || ($info['mime'] ?? '') !== $mime || $info[0] < 1 || $info[1] < 1 || $info[0] > $max || $info[1] > $max) {
                $this->fail(__('orders.proof_invalid'));
            }
            if (function_exists('imagecreatefromstring') && $info[0] * $info[1] <= 16_000_000) { // must really decode (polyglots / truncated files)
                $img = @imagecreatefromstring((string) file_get_contents($path));
                if ($img === false) {
                    $this->fail(__('orders.proof_invalid'));
                }
                imagedestroy($img);
            }
        }

        return ['hash' => hash_file('sha256', $path), 'ext' => self::EXT[$mime], 'mime' => $mime];
    }

    /** A proof already attached to ANOTHER live order of the tenant is a reused proof (rejected / cancelled orders do not count). */
    public function assertNotReused(string $hash, ?int $exceptOrderId = null): void
    {
        $reused = Order::query()->where('payment_proof_hash', $hash)
            ->when($exceptOrderId, fn ($q) => $q->where('id', '!=', $exceptOrderId))
            ->where(fn ($q) => $q->whereNull('proof_status')->orWhere('proof_status', '!=', ProofStatus::REJECTED->value))
            ->whereNotIn('status', [OrderStatus::CANCELLED->value])
            ->exists();
        if ($reused) {
            $this->fail(__('orders.proof_reused'));
        }
    }

    /** True when a QueryException is the orders.proof_guard unique index (a concurrent order won the same proof hash). */
    public static function isReuseViolation(\Throwable $e): bool
    {
        return $e instanceof \Illuminate\Database\UniqueConstraintViolationException && str_contains($e->getMessage(), 'orders_proof_guard_unique');
    }

    /** Stores the already inspected file on the private disk; returns the relative path. */
    public function store(UploadedFile $file, int $tenantId, array $info): string
    {
        $path = 'tenants/'.$tenantId.'/payment-proofs/'.Str::random(40).'.'.$info['ext'];
        Storage::disk(self::DISK)->put($path, (string) file_get_contents($file->getRealPath()));

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public function reused(): never
    {
        $this->fail(__('orders.proof_reused'));
    }

    private function fail(string $msg): never
    {
        throw ValidationException::withMessages(['proof' => $msg]);
    }
}
