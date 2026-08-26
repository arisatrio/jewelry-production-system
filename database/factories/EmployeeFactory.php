<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Employee::DEPARTMENT_PRODUCTION,
            'nama_lengkap' => fake()->unique()->name(),
            'nama_panggilan' => fake()->firstName(),
            'status' => Employee::STATUS_ACTIVE,
            'is_deleted' => 0,
        ];
    }

    /**
     * Active PRODUCTION department employee.
     */
    public function productionActive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'department_id' => Employee::DEPARTMENT_PRODUCTION,
            'status' => Employee::STATUS_ACTIVE,
            'is_deleted' => 0,
        ]);
    }
}
