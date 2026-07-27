<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'   => User::factory(),
            'disk'      => 'public',
            'path'      => 'users/1/uploads/' . $this->faker->uuid() . '.bin',
            'filename'  => $this->faker->word() . '.bin',
            'mime_type' => 'application/octet-stream',
            'size'      => 1024,
        ];
    }
}
