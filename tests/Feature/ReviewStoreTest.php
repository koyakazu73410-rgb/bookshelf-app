<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ReviewStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_store_review(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $book = Book::factory()->create();

        $data = [
            'rating' => 5,
            'comment' => 'とてもわかりやすい本でした',
        ];

        $response = $this->post(
            route('reviews.store', $book),
            $data
        );

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => 'とてもわかりやすい本でした',
        ]);
    }
}
