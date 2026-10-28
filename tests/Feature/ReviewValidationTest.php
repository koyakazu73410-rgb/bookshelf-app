<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_store_rejects_missing_required_fields(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->post(route('reviews.store', Book::factory()->create()), []);

        $response->assertSessionHasErrors(['rating', 'comment']);
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_review_store_rejects_rating_outside_one_to_five(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        foreach ([0, 6] as $rating) {
            $response = $this->actingAs($user)->post(route('reviews.store', $book), [
                'rating' => $rating,
                'comment' => '評価範囲外のレビュー',
            ]);

            $response->assertSessionHasErrors('rating');
        }

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_review_store_rejects_non_integer_rating_and_overlong_comment(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->post(route('reviews.store', Book::factory()->create()), [
                'rating' => 'five',
                'comment' => str_repeat('あ', 1001),
            ]);

        $response->assertSessionHasErrors(['rating', 'comment']);
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_review_update_validation_failure_does_not_change_database(): void
    {
        $owner = User::factory()->create();
        $review = Review::factory()->for($owner)->create([
            'rating' => 4,
            'comment' => '更新前コメント',
        ]);

        $response = $this->actingAs($owner)
            ->put(route('reviews.update', $review), [
                'rating' => 7,
                'comment' => '',
            ]);

        $response->assertSessionHasErrors(['rating', 'comment']);
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => 4,
            'comment' => '更新前コメント',
        ]);
    }
}
