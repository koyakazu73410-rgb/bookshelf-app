<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewLikeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ログイン済みユーザーは他ユーザーのレビューにいいねできる(): void
    {
        $reviewAuthor = User::factory()->create();
        $likingUser = User::factory()->create();
        $book = Book::factory()->create();
        $review = Review::factory()->create([
            'user_id' => $reviewAuthor->id,
            'book_id' => $book->id,
        ]);

        $response = $this->actingAs($likingUser)
            ->post(route('reviews.like', $review));

        $response->assertSessionHas('success', 'レビューにいいねしました。');
        $this->assertDatabaseHas('review_likes', [
            'user_id' => $likingUser->id,
            'review_id' => $review->id,
        ]);
    }

    public function test_いいね済みレビューのいいねを解除できる(): void
    {
        $reviewAuthor = User::factory()->create();
        $likingUser = User::factory()->create();
        $book = Book::factory()->create();
        $review = Review::factory()->create([
            'user_id' => $reviewAuthor->id,
            'book_id' => $book->id,
        ]);
        $likingUser->likedReviews()->attach($review->id);

        $response = $this->actingAs($likingUser)
            ->post(route('reviews.like', $review));

        $response->assertSessionHas('success', 'レビューのいいねを解除しました。');
        $this->assertDatabaseMissing('review_likes', [
            'user_id' => $likingUser->id,
            'review_id' => $review->id,
        ]);
    }

    public function test_自分自身のレビューにはいいねできない(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $review = Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        $this->actingAs($user)
            ->post(route('reviews.like', $review));

        $this->assertDatabaseMissing('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);
    }
}
