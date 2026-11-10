<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\Genre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookApiSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_keyword_search_matches_title_or_author_and_excludes_non_matches(): void
    {
        Book::factory()->create(['title' => 'Laravel実践入門', 'author' => '著者A']);
        Book::factory()->create(['title' => '別の書籍', 'author' => 'Laravel研究会']);
        Book::factory()->create(['title' => 'PHP入門', 'author' => '著者B']);

        $response = $this->getJson('/api/v1/books?keyword=Laravel');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['title' => 'Laravel実践入門'])
            ->assertJsonFragment(['author' => 'Laravel研究会'])
            ->assertJsonMissing(['title' => 'PHP入門']);
    }

    public function test_genre_filter_returns_only_books_in_selected_genre(): void
    {
        $selectedGenre = Genre::factory()->create();
        $otherGenre = Genre::factory()->create();
        $matchingBook = Book::factory()->create(['title' => '対象書籍']);
        $nonMatchingBook = Book::factory()->create(['title' => '対象外書籍']);
        $matchingBook->genres()->attach($selectedGenre);
        $nonMatchingBook->genres()->attach($otherGenre);

        $this->getJson("/api/v1/books?genre_id={$selectedGenre->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id' => $matchingBook->id])
            ->assertJsonMissing(['id' => $nonMatchingBook->id]);
    }

    public function test_pagination_uses_requested_page_and_per_page(): void
    {
        Book::factory()->count(12)->create();

        $response = $this->getJson('/api/v1/books?page=2&per_page=5');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 12);
    }
}
