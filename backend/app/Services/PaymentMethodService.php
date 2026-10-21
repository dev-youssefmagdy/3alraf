<?php

namespace App\Services;

use App\Repositories\Contracts\PaymentMethodRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class PaymentMethodService extends CrudService
{
    public function __construct(PaymentMethodRepositoryInterface $repo)
    {
        parent::__construct($repo);
    }

    /** Credentials are stored encrypted (model cast). Blank secret on edit keeps the stored value; switching provider drops the old ones. */
    protected function payload(object $dto, ?Model $model = null): array
    {
        $data = $dto->toArray();
        $existing = ($model && $model->provider === $dto->provider) ? ($model->credentials ?? []) : [];
        $creds = array_replace($existing, $dto->credentials);
        $data['credentials'] = $creds ?: null;

        return $data;
    }
}
