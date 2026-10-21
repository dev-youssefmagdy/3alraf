<?php

namespace App\Services;

use App\Models\Customer;

class CustomerAccountService
{
    public function register(array $data): Customer
    {
        return Customer::create($data + ['is_active' => true]);
    }

    public function updateProfile(Customer $c, array $data): Customer
    {
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $c->update($data);

        return $c;
    }
}
