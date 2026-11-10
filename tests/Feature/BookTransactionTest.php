<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class BookTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_commits_book_and_genre_assignment_together(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $response = $this->actingAs($user)->post(route('books.store'), [
            'title' => 'トランザクション登録テスト',
            'author' => '登録著者',
            'isbn' => '9781234567807',
            'published_date' => '2026-11-08',
            'genres' => [$genre->id],
        ]);

        $book = Book::query()->where('isbn', '9781234567807')->firstOrFail();

        $response->assertRedirect(route('books.show', $book))
            ->assertSessionHas('success', '書籍を登録しました。');
        $this->assertSame($user->id, $book->user_id);
        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $genre->id,
        ]);
    }

    public function test_store_rolls_back_book_when_genre_assignment_throws_an_exception(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $failPivotInsert = true;

        DB::listen(function (QueryExecuted $query) use (&$failPivotInsert): void {
            if ($failPivotInsert && $this->isBookGenreInsert($query->sql)) {
                throw new RuntimeException('Simulated genre assignment failure.');
            }
        });

        try {
            $this->withoutExceptionHandling()->actingAs($user)->post(route('books.store'), [
                'title' => 'ロールバック対象書籍',
                'author' => '登録著者',
                'isbn' => '9781234567814',
                'published_date' => '2026-11-08',
                'genres' => [$genre->id],
            ]);

            $this->fail('The simulated genre assignment exception was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated genre assignment failure.', $exception->getMessage());
        } finally {
            $failPivotInsert = false;
        }

        $this->assertDatabaseMissing('books', ['isbn' => '9781234567814']);
        $this->assertDatabaseCount('book_genre', 0);
    }

    public function test_update_commits_book_and_synchronized_genres_together(): void
    {
        $owner = User::factory()->create();
        $oldGenre = Genre::factory()->create();
        $newGenre = Genre::factory()->create();
        $book = Book::factory()->for($owner)->create(['title' => '更新前']);
        $book->genres()->attach($oldGenre);

        $response = $this->actingAs($owner)->put(route('books.update', $book), [
            'title' => '更新後',
            'author' => $book->author,
            'isbn' => $book->isbn,
            'published_date' => $book->published_date,
            'genres' => [$newGenre->id],
        ]);

        $response->assertRedirect(route('books.show', $book))
            ->assertSessionHas('success', '書籍を更新しました。');
        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => '更新後']);
        $this->assertDatabaseMissing('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $oldGenre->id,
        ]);
        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $newGenre->id,
        ]);
    }

    public function test_update_rolls_back_book_and_genres_when_sync_throws_an_exception(): void
    {
        $owner = User::factory()->create();
        $oldGenre = Genre::factory()->create();
        $newGenre = Genre::factory()->create();
        $book = Book::factory()->for($owner)->create(['title' => '更新前']);
        $book->genres()->attach($oldGenre);
        $failPivotInsert = true;

        DB::listen(function (QueryExecuted $query) use (&$failPivotInsert): void {
            if ($failPivotInsert && $this->isBookGenreInsert($query->sql)) {
                throw new RuntimeException('Simulated genre synchronization failure.');
            }
        });

        try {
            $this->withoutExceptionHandling()->actingAs($owner)->put(route('books.update', $book), [
                'title' => 'ロールバックされる更新',
                'author' => $book->author,
                'isbn' => $book->isbn,
                'published_date' => $book->published_date,
                'genres' => [$newGenre->id],
            ]);

            $this->fail('The simulated genre synchronization exception was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated genre synchronization failure.', $exception->getMessage());
        } finally {
            $failPivotInsert = false;
        }

        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => '更新前']);
        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $oldGenre->id,
        ]);
        $this->assertDatabaseMissing('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $newGenre->id,
        ]);
    }

    private function isBookGenreInsert(string $sql): bool
    {
        return str_starts_with(strtolower(ltrim($sql)), 'insert')
            && str_contains($sql, 'book_genre');
    }
}
