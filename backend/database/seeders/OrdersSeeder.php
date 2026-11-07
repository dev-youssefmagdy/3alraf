<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\DiscountCode;
use Illuminate\Database\Seeder;

/** Demo orders-phase data (idempotent): discount code + customer (customer@egy.test / password). */
class OrdersSeeder extends Seeder
{
    public function run(): void
    {
        DiscountCode::query()->firstOrCreate(['code' => 'WELCOME10'],
            ['type' => 'percent', 'value' => 10, 'min_order' => 100, 'max_discount' => 50, 'scope' => 'all', 'is_active' => true]);
        Customer::query()->firstOrCreate(['email' => 'customer@egy.test'],
            ['name' => 'Demo Customer', 'phone' => '01000000001', 'password' => 'password', 'is_active' => true]);
    }
}
