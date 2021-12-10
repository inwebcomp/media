<?php

namespace InWeb\Media\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use InWeb\Media\Tests\TestEntity;

class TestEntityFactory extends Factory
{
    protected $model = TestEntity::class;

    public function definition(): array
    {
        return [];
    }
}
