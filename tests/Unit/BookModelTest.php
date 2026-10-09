<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Book;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookModelTest extends TestCase
{
    use RefreshDatabase;
    public function test_book_has_genres_relationship(): void
    {
        $book = new Book();

        $this->assertInstanceOf(
            BelongsToMany::class,
            $book->genres()
        );
    }

    public function test_book_has_reviews_relationship(): void
    {
        $book = new Book();

        $this->assertInstanceOf(
            HasMany::class,
            $book->reviews()
        );
    }
}
