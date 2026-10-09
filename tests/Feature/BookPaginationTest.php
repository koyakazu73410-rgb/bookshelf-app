<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_書籍一覧は1ページ10冊表示される(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Book::factory()->count(11)->create([
            'user_id' => $user->id,
        ]);


        $response = $this->get(route('books.index'));

        $response->assertStatus(200);

        $response->assertViewHas('books', function ($books) {
            return $books->count() === 10;
        });

        $response = $this->get(route('books.index', ['page' => 2]));

        $response->assertStatus(200);

        $response->assertViewHas('books', function ($books) {
            return $books->count() === 1;
            });
    }
}
