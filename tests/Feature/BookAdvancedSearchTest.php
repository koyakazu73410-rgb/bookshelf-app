<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookAdvancedSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_book_index_is_displayed_without_search_conditions(): void
    {
        Book::factory()->create(['title' => '一覧表示対象']);

        $this->get(route('books.index'))
            ->assertOk()
            ->assertSee('一覧表示対象')
            ->assertViewHasAll(['books', 'genres']);
    }

    public function test_keyword_search_matches_book_title_or_author(): void
    {
        Book::factory()->create(['title' => 'Laravel入門', 'author' => '著者A']);
        Book::factory()->create(['title' => '別の書籍', 'author' => 'Laravel研究会']);
        Book::factory()->create(['title' => '対象外', 'author' => '著者B']);

        $this->get(route('books.index', ['keyword' => 'Laravel']))
            ->assertOk()
            ->assertSee('Laravel入門')
            ->assertSee('Laravel研究会')
            ->assertDontSee('対象外');
    }

    public function test_genre_filter_returns_only_books_in_selected_genre(): void
    {
        $selectedGenre = Genre::factory()->create();
        $otherGenre = Genre::factory()->create();
        $matchingBook = Book::factory()->create(['title' => '対象書籍']);
        $nonMatchingBook = Book::factory()->create(['title' => '対象外書籍']);
        $matchingBook->genres()->attach($selectedGenre);
        $nonMatchingBook->genres()->attach($otherGenre);

        $this->get(route('books.index', ['genre' => $selectedGenre->id]))
            ->assertOk()
            ->assertSee('対象書籍')
            ->assertDontSee('対象外書籍');
    }

    public function test_books_can_be_sorted_by_oldest_title_and_rating(): void
    {
        $olderBook = Book::factory()->create(['title' => 'B書籍', 'created_at' => now()->subDay()]);
        $newerBook = Book::factory()->create(['title' => 'A書籍', 'created_at' => now()]);
        $unratedBook = Book::factory()->create(['title' => 'C書籍']);
        Review::factory()->for($olderBook)->create(['rating' => 3]);
        Review::factory()->for($newerBook)->create(['rating' => 5]);

        $this->get(route('books.index', ['sort' => 'oldest']))
            ->assertViewHas('books', fn ($books) => $books->first()->is($olderBook));

        $this->get(route('books.index', ['sort' => 'title']))
            ->assertViewHas('books', fn ($books) => $books->pluck('title')->all() === ['A書籍', 'B書籍', 'C書籍']);

        $this->get(route('books.index', ['sort' => 'rating']))
            ->assertViewHas('books', fn ($books) => $books->pluck('id')->all() === [
                $newerBook->id,
                $olderBook->id,
                $unratedBook->id,
            ]);
    }

    public function test_pagination_links_keep_search_filter_and_sort_conditions(): void
    {
        $genre = Genre::factory()->create();
        $books = Book::factory()->count(11)->create([
            'title' => 'Laravel検索対象',
        ]);
        $genre->books()->attach($books->pluck('id'));

        $this->get(route('books.index', [
            'keyword' => 'Laravel',
            'genre' => $genre->id,
            'sort' => 'oldest',
        ]))->assertOk()->assertViewHas('books', function ($paginatedBooks) use ($genre) {
            $pageTwoUrl = $paginatedBooks->url(2);

            return str_contains($pageTwoUrl, 'keyword=Laravel')
                && str_contains($pageTwoUrl, 'genre='.$genre->id)
                && str_contains($pageTwoUrl, 'sort=oldest');
        });
    }

    public function test_empty_search_result_displays_not_found_message(): void
    {
        Book::factory()->create(['title' => '既存の書籍']);

        $this->get(route('books.index', ['keyword' => '一致しないキーワード']))
            ->assertOk()
            ->assertSee('書籍が見つかりませんでした。')
            ->assertDontSee('既存の書籍');
    }

    public function test_keyword_and_genre_filters_are_applied_together(): void
    {
        $selectedGenre = Genre::factory()->create();
        $otherGenre = Genre::factory()->create();
        $matchingBook = Book::factory()->create(['title' => 'Laravel実践']);
        $keywordOnlyBook = Book::factory()->create(['title' => 'Laravel入門']);
        $genreOnlyBook = Book::factory()->create(['title' => 'PHP実践']);
        $matchingBook->genres()->attach($selectedGenre);
        $keywordOnlyBook->genres()->attach($otherGenre);
        $genreOnlyBook->genres()->attach($selectedGenre);

        $this->get(route('books.index', [
            'keyword' => 'Laravel',
            'genre' => $selectedGenre->id,
        ]))
            ->assertOk()
            ->assertSee('Laravel実践')
            ->assertDontSee('Laravel入門')
            ->assertDontSee('PHP実践');
    }

    public function test_nonexistent_genre_id_displays_empty_result_without_server_error(): void
    {
        Book::factory()->create(['title' => '既存の書籍']);

        $this->get(route('books.index', ['genre' => 999999]))
            ->assertOk()
            ->assertSee('書籍が見つかりませんでした。')
            ->assertDontSee('既存の書籍');
    }

    public function test_array_keyword_does_not_cause_internal_server_error(): void
    {
        $this->get(route('books.index', ['keyword' => ['Laravel']]))
            ->assertStatus(400);
    }

    public function test_array_genre_does_not_cause_internal_server_error(): void
    {
        $this->get(route('books.index', ['genre' => [1]]))
            ->assertStatus(400);
    }

    public function test_array_sort_does_not_cause_internal_server_error(): void
    {
        $this->get(route('books.index', ['sort' => ['latest']]))
            ->assertStatus(400);
    }

    public function test_undefined_sort_value_falls_back_to_latest_order(): void
    {
        $olderBook = Book::factory()->create([
            'title' => '古い書籍',
            'created_at' => now()->subDay(),
        ]);
        $newerBook = Book::factory()->create([
            'title' => '新しい書籍',
            'created_at' => now(),
        ]);

        $this->get(route('books.index', ['sort' => 'undefined']))
            ->assertOk()
            ->assertViewHas('books', fn ($books) => $books->pluck('id')->all() === [
                $newerBook->id,
                $olderBook->id,
            ]);
    }

    public function test_latest_sort_displays_newest_books_first(): void
    {
        $olderBook = Book::factory()->create([
            'title' => '古い書籍',
            'created_at' => now()->subDay(),
        ]);
        $newerBook = Book::factory()->create([
            'title' => '新しい書籍',
            'created_at' => now(),
        ]);

        $this->get(route('books.index', ['sort' => 'latest']))
            ->assertOk()
            ->assertViewHas('books', fn ($books) => $books->pluck('id')->all() === [
                $newerBook->id,
                $olderBook->id,
            ]);
    }
}
