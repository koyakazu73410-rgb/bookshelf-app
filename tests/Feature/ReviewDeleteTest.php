<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ReviewDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_delete_own_review(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $book = Book::factory()->create();

        $review = Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        $response = $this->delete(
            route('reviews.destroy', $review)
        );

        $this->assertDatabaseMissing('reviews', [
            'id' => $review->id,
        ]);

        $response->assertRedirect(route('books.show', $book));
    }
}
