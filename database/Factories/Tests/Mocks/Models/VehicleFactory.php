<?php

namespace Database\Factories\Tests\Mocks\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tests\Mocks\Models\Vehicle;

class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition()
    {
        return [
            'vin' => '11111111111111111'
        ];
    }
}