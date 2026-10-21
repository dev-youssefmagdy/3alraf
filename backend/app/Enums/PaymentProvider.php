<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum PaymentProvider: string
{
    use HasLabels;

    case COD = 'cod';
    case VODAFONE_CASH = 'vodafone_cash';
    case INSTAPAY = 'instapay';
    case ETISALAT_CASH = 'etisalat_cash';
    case WALLET = 'wallet'; // any other admin-defined offline wallet / bank transfer
    case PAYMOB = 'paymob';
    case FAWRYPAY = 'fawrypay';
    case PAYPAL = 'paypal';
    case PAYTABS = 'paytabs';
    case STRIPE = 'stripe';

    public function type(): PaymentMethodType
    {
        return match ($this) {
            self::COD => PaymentMethodType::COD,
            self::VODAFONE_CASH, self::INSTAPAY, self::ETISALAT_CASH, self::WALLET => PaymentMethodType::WALLET,
            default => PaymentMethodType::ONLINE,
        };
    }

    /** Online providers with a real driver; the others are stubs ("not implemented") and are never offered at checkout. */
    public function implemented(): bool
    {
        return $this->type() !== PaymentMethodType::ONLINE || in_array($this, [self::PAYMOB, self::FAWRYPAY], true);
    }

    /** @return array<string,bool> credential field => required */
    public function credentialFields(): array
    {
        return match ($this) {
            self::PAYMOB => ['api_key' => true, 'integration_id' => true, 'iframe_id' => true, 'hmac_secret' => true],
            self::FAWRYPAY => ['merchant_code' => true, 'secure_key' => true],
            self::PAYPAL => ['client_id' => true, 'client_secret' => true, 'webhook_id' => false],
            self::PAYTABS => ['profile_id' => true, 'server_key' => true, 'region' => false],
            self::STRIPE => ['publishable_key' => true, 'secret_key' => true, 'webhook_secret' => true],
            default => [],
        };
    }

    /** @return array<string,string> provider => label, restricted to a method type */
    public static function forType(PaymentMethodType $type): array
    {
        return collect(self::cases())->filter(fn ($p) => $p->type() === $type)->mapWithKeys(fn ($p) => [$p->value => $p->label()])->all();
    }
}
