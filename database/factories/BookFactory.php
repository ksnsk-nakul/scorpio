<?php

namespace Database\Factories;

use App\Models\Author;
use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BookFactory extends Factory
{
    protected $model = Book::class;

    public function definition(): array
    {
        return [
            'author_id' => Author::factory(),
            'title' => $this->faker->sentence(3),
            'source_epub_path' => 'books/uploads/' . Str::uuid() . '.epub',
            'status' => 'ready',
            'uploaded_by' => User::factory(),
        ];
    }
}
