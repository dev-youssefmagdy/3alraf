<?php

namespace App\DTOs;

use App\Enums\FeeBearer;
use App\Enums\PaymentMethodType;
use App\Enums\PaymentProvider;
use App\Http\Requests\Tenant\PaymentMethodRequest;

final readonly class PaymentMethodData
{
    public function __construct(
        public PaymentMethodType $type, public PaymentProvider $provider, public array $name, public array $instructions, public ?string $accountNumber,
        public bool $requiresProof, public string $feePercent, public string $feeFixed, public FeeBearer $feeBearer, public array $credentials,
        public bool $isSandbox, public bool $isActive, public int $sortOrder,
    ) {}

    public static function fromRequest(PaymentMethodRequest $r): self
    {
        $type = PaymentMethodType::from($r->input('type'));
        $provider = PaymentProvider::from($r->input('provider'));
        $creds = $type === PaymentMethodType::ONLINE
            ? collect($r->input('credentials', []))->only(array_keys($provider->credentialFields()))->filter(fn ($v) => $v !== null && $v !== '')->all()
            : [];

        return new self(
            $type, $provider, $r->cleanTranslations($r->input('name')), $r->cleanTranslations($r->input('instructions')),
            $type === PaymentMethodType::WALLET ? $r->input('account_number') : null,
            $type === PaymentMethodType::WALLET && $r->boolean('requires_proof'),
            (string) $r->input('fee_percent', 0), (string) $r->input('fee_fixed', 0), FeeBearer::from($r->input('fee_bearer')), $creds,
            $r->boolean('is_sandbox'), $r->boolean('is_active'), (int) $r->input('sort_order', 0),
        );
    }

    public function toArray(): array
    {
        return ['type' => $this->type, 'provider' => $this->provider, 'name' => $this->name, 'instructions' => $this->instructions ?: null,
            'account_number' => $this->accountNumber, 'requires_proof' => $this->requiresProof, 'fee_percent' => $this->feePercent, 'fee_fixed' => $this->feeFixed,
            'fee_bearer' => $this->feeBearer, 'is_sandbox' => $this->isSandbox, 'is_active' => $this->isActive, 'sort_order' => $this->sortOrder];
    }
}
