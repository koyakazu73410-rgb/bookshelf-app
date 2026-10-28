<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_book_index_is_ordered_from_newest_to_oldest(): void
    {
        $olderBook = Book::factory()->create([
            'title' => '古い書籍',
            'created_at' => now()->subDay(),
        ]);
        $newerBook = Book::factory()->create([
            'title' => '新しい書籍',
            'created_at' => now(),
        ]);

        $this->get(route('books.index'))
            ->assertOk()
            ->assertViewHas('books', fn ($books) => $books->pluck('id')->all() === [
                $newerBook->id,
                $olderBook->id,
            ]);
    }

    public function test_book_index_contains_calculated_average_rating(): void
    {
        $book = Book::factory()->create();
        Review::factory()->for($book)->create(['rating' => 5]);
        Review::factory()->for($book)->create(['rating' => 3]);

        $this->get(route('books.index'))
            ->assertOk()
            ->assertViewHas('books', function ($books) use ($book) {
                $listedBook = $books->firstWhere('id', $book->id);

                return (float) $listedBook->reviews_avg_rating === 4.0;
            });
    }

    public function test_book_detail_loads_review_user_like_count_and_current_user_like_state(): void
    {
        $reviewer = User::factory()->create();
        $viewer = User::factory()->create();
        $book = Book::factory()->create();
        $review = Review::factory()->for($reviewer)->for($book)->create();
        $review->likedUsers()->attach($viewer);

        $this->actingAs($viewer)->get(route('books.show', $book))
            ->assertOk()
            ->assertViewHas('book', function ($shownBook) use ($reviewer) {
                $shownReview = $shownBook->reviews->first();

                return $shownReview->relationLoaded('user')
                    && $shownReview->user->is($reviewer)
                    && $shownReview->liked_users_count === 1
                    && (bool) $shownReview->liked_by_current_user;
            });
    }
}
