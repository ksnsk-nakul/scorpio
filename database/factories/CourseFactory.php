<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        return [
            'code' => 'C'.$this->faker->unique()->numberBetween(100, 999).'-TEST-101',
            'title' => $this->faker->sentence(3),
            'source_path' => '/tmp/fake',
            'status' => 'ready',
            'imported_at' => now(),
        ];
    }
}
