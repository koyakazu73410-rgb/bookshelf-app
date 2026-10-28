<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CascadeDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_book_removes_its_pivot_reviews_favorites_and_review_likes(): void
    {
        $owner = User::factory()->create();
        $favoriteUser = User::factory()->create();
        $reviewer = User::factory()->create();
        $likeUser = User::factory()->create();
        $book = Book::factory()->for($owner)->create();
        $genre = Genre::factory()->create();
        $review = Review::factory()->for($reviewer)->for($book)->create();

        $book->genres()->attach($genre);
        $favoriteUser->favoriteBooks()->attach($book);
        $review->likedUsers()->attach($likeUser);

        $this->actingAs($owner)
            ->delete(route('books.destroy', $book))
            ->assertRedirect(route('books.index'));

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('favorites', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
        $this->assertDatabaseMissing('review_likes', ['review_id' => $review->id]);
    }

    public function test_deleting_review_removes_its_review_likes(): void
    {
        $reviewer = User::factory()->create();
        $likeUser = User::factory()->create();
        $review = Review::factory()->for($reviewer)->create();
        $review->likedUsers()->attach($likeUser);

        $this->actingAs($reviewer)
            ->delete(route('reviews.destroy', $review))
            ->assertRedirect(route('books.show', $review->book_id));

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
        $this->assertDatabaseMissing('review_likes', ['review_id' => $review->id]);
    }
}
