<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ReviewUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_own_review(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $book = Book::factory()->create();

        $review = Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 3,
            'comment' => '更新前のレビューです。',
        ]);

        $data = [
            'rating' => 5,
            'comment' => '更新後のレビューです。',
        ];

        $response = $this->put(
            route('reviews.update', $review),
            $data
        );

        $this->assertDatabaseHas('reviews', [
            'rating' => 5,
            'comment' => '更新後のレビューです。',
        ]);

        $response->assertRedirect(route('books.show', $book));
    }
}
