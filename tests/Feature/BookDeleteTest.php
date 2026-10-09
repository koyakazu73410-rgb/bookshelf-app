<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class BookDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_delete_own_book(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $book = Book::factory()->create([
            'user_id' => $user->id
        ]);

        $response = $this->delete(
            route('books.destroy', $book)
        );

        $this->assertDatabaseMissing('books', [
            'id' => $book->id,
        ]);

        $response->assertRedirect(route('books.index'));
    }
}
