<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_protected_book_and_user_actions(): void
    {
        $book = Book::factory()->create();
        $review = Review::factory()->for($book)->create();

        $requests = [
            $this->get(route('books.create')),
            $this->post(route('books.store')),
            $this->get(route('books.edit', $book)),
            $this->put(route('books.update', $book)),
            $this->delete(route('books.destroy', $book)),
            $this->post(route('reviews.store', $book)),
            $this->get(route('reviews.edit', $review)),
            $this->put(route('reviews.update', $review)),
            $this->delete(route('reviews.destroy', $review)),
            $this->post(route('favorites.toggle', $book)),
            $this->get(route('favorites.index')),
            $this->post(route('reviews.like', $review)),
        ];

        foreach ($requests as $response) {
            $response->assertRedirect(route('login'));
        }
    }

    public function test_other_user_cannot_edit_update_or_delete_book(): void
    {
        $book = Book::factory()->create();
        $otherUser = User::factory()->create();
        $genre = Genre::factory()->create();
        $payload = [
            'title' => '不正更新',
            'author' => '不正ユーザー',
            'isbn' => $book->isbn,
            'published_date' => '2025-01-01',
            'genres' => [$genre->id],
        ];

        $this->actingAs($otherUser)->get(route('books.edit', $book))->assertForbidden();
        $this->actingAs($otherUser)->put(route('books.update', $book), $payload)->assertForbidden();
        $this->actingAs($otherUser)->delete(route('books.destroy', $book))->assertForbidden();

        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    public function test_other_user_cannot_edit_update_or_delete_review(): void
    {
        $review = Review::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)->get(route('reviews.edit', $review))->assertForbidden();
        $this->actingAs($otherUser)->put(route('reviews.update', $review), [
            'rating' => 5,
            'comment' => '不正更新',
        ])->assertForbidden();
        $this->actingAs($otherUser)->delete(route('reviews.destroy', $review))->assertForbidden();

        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }
}
