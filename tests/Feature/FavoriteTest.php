<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_ログイン済みユーザーは書籍をお気に入り登録できる(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('favorites.toggle', $book));

        $response->assertSessionHas('success', 'お気に入りに追加しました。');
        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    public function test_お気に入り登録済みの書籍を解除できる(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $user->favoriteBooks()->attach($book->id);

        $response = $this->actingAs($user)
            ->post(route('favorites.toggle', $book));

        $response->assertSessionHas('success', 'お気に入りを解除しました。');
        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    public function test_ログイン済みユーザーはお気に入り一覧で登録書籍を確認できる(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create([
            'title' => 'お気に入り表示確認用書籍',
        ]);
        $user->favoriteBooks()->attach($book->id);

        $response = $this->actingAs($user)
            ->get(route('favorites.index'));

        $response->assertOk();
        $response->assertViewIs('favorites.index');
        $response->assertViewHas('books', function ($books) use ($book) {
            return $books->contains('id', $book->id);
        });
        $response->assertSee('お気に入り表示確認用書籍');
    }
}
