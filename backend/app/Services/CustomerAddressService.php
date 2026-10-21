<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Repositories\Contracts\CustomerAddressRepositoryInterface;
use Illuminate\Support\Collection;

class CustomerAddressService
{
    public function __construct(private CustomerAddressRepositoryInterface $addresses) {}

    public function forCustomer(int $customerId): Collection
    {
        return $this->addresses->query()->with('city')->where('customer_id', $customerId)->orderByDesc('is_default')->orderBy('id')->get();
    }

    public function save(Customer $customer, array $data, ?int $id = null): CustomerAddress
    {
        $data['is_default'] = (bool) ($data['is_default'] ?? false) || ! $customer->addresses()->exists();
        /** @var CustomerAddress $a */
        $a = $id ? $this->addresses->update($customer->addresses()->findOrFail($id), $data) : $this->addresses->create($data + ['customer_id' => $customer->id]);
        if ($a->is_default) {
            $customer->addresses()->where('id', '!=', $a->id)->update(['is_default' => false]);
        }

        return $a;
    }

    public function delete(Customer $customer, int $id): void
    {
        $this->addresses->delete($customer->addresses()->findOrFail($id));
        if (! $customer->addresses()->where('is_default', true)->exists() && $first = $customer->addresses()->first()) {
            $first->update(['is_default' => true]);
        }
    }
}
