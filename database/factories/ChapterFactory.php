<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Chapter;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChapterFactory extends Factory
{
    protected $model = Chapter::class;

    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'title' => $this->faker->sentence(2),
            'content' => '<p>' . $this->faker->paragraph() . '</p>',
            'sort_order' => 0,
        ];
    }
}
