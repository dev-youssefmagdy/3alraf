<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethodType;
use App\Enums\PaymentProvider;
use App\Models\PaymentMethod;
use App\Services\Payments\Drivers\FawryPayGateway;
use App\Services\Payments\Drivers\PaymobGateway;
use App\Services\Payments\Drivers\PayPalGateway;
use App\Services\Payments\Drivers\PayTabsGateway;
use App\Services\Payments\Drivers\StripeGateway;
use InvalidArgumentException;

class PaymentGatewayFactory
{
    /** @var array<string,class-string<AbstractGateway>> */
    private const DRIVERS = [
        'paymob' => PaymobGateway::class,
        'fawrypay' => FawryPayGateway::class,
        'paypal' => PayPalGateway::class,
        'paytabs' => PayTabsGateway::class,
        'stripe' => StripeGateway::class,
    ];

    public function make(PaymentMethod $method): PaymentGatewayInterface
    {
        if ($method->type !== PaymentMethodType::ONLINE || ! isset(self::DRIVERS[$method->provider->value])) {
            throw new InvalidArgumentException("Payment method [{$method->provider->value}] has no online gateway driver.");
        }
        $class = self::DRIVERS[$method->provider->value];

        return new $class($method);
    }

    /** @return string[] */
    public function supported(): array
    {
        return array_keys(self::DRIVERS);
    }
}
