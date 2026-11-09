<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guest_is_redirected_to_login_from_report(): void
    {
        $this->get(route('reports.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_display_report(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertViewIs('reports.index')
            ->assertSee('マイ読書レポート')
            ->assertSee('マイレポート');
    }

    public function test_report_excludes_other_users_reviews(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Review::factory()->for($user)->create(['rating' => 5]);
        Review::factory()->for($otherUser)->count(2)->create(['rating' => 1]);

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertViewHas('stats', fn (array $stats): bool => $stats['summary']['total_reviews'] === 1
                && (float) $stats['summary']['average_rating'] === 5.0
                && $stats['rating_distribution']->all() === [0, 0, 0, 0, 1]
            );
    }

    public function test_report_calculates_total_reviews_and_average_rating(): void
    {
        $user = User::factory()->create();
        Review::factory()->for($user)->create(['rating' => 3]);
        Review::factory()->for($user)->create(['rating' => 5]);

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertViewHas('stats', fn (array $stats): bool => $stats['summary']['total_reviews'] === 2
                && (float) $stats['summary']['average_rating'] === 4.0
            )
            ->assertSee('4.0');
    }

    public function test_books_read_counts_each_book_only_once(): void
    {
        $user = User::factory()->create();
        $firstBook = Book::factory()->create();
        $secondBook = Book::factory()->create();
        Review::factory()->for($user)->for($firstBook)->count(2)->create();
        Review::factory()->for($user)->for($secondBook)->create();

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertViewHas('stats', fn (array $stats): bool => $stats['summary']['total_reviews'] === 3
                && $stats['summary']['books_read'] === 2
            );
    }

    public function test_rating_distribution_contains_every_rating_from_one_to_five(): void
    {
        $user = User::factory()->create();
        foreach ([1, 1, 3, 5] as $rating) {
            Review::factory()->for($user)->create(['rating' => $rating]);
        }

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertViewHas('stats', fn (array $stats): bool => $stats['rating_distribution'] instanceof Collection
                && $stats['rating_distribution']->all() === [2, 0, 1, 0, 1]
            )
            ->assertSee('rating-1', false)
            ->assertSee('rating-5', false);
    }

    public function test_report_displays_zero_state_when_user_has_no_reviews(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats): bool => $stats['summary'] === [
                'total_reviews' => 0,
                'books_read' => 0,
                'average_rating' => 0,
            ]
                && $stats['rating_distribution']->all() === [0, 0, 0, 0, 0]
            )
            ->assertSee('data-testid="average-rating"', false)
            ->assertSee('レビューした書籍がありません')
            ->assertSee('ジャンルが設定された書籍のレビューがありません');
    }

    public function test_top_rated_books_include_books_with_average_rating_below_four(): void
    {
        $user = User::factory()->create();
        $highRatedBook = Book::factory()->create(['title' => '高評価書籍']);
        $lowRatedBook = Book::factory()->create(['title' => '低評価書籍']);
        Review::factory()->for($user)->for($highRatedBook)->create(['rating' => 4]);
        Review::factory()->for($user)->for($lowRatedBook)->create(['rating' => 3]);

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertViewHas('stats', fn (array $stats): bool => $stats['top_rated_books']->pluck('id')->all() === [
                $highRatedBook->id,
                $lowRatedBook->id,
            ]
            )
            ->assertSee('高評価書籍')
            ->assertSee('低評価書籍');
    }

    public function test_top_rated_books_are_ordered_by_rating_descending(): void
    {
        $user = User::factory()->create();
        $fourStarBook = Book::factory()->create();
        $fiveStarBook = Book::factory()->create();
        Review::factory()->for($user)->for($fourStarBook)->create(['rating' => 4]);
        Review::factory()->for($user)->for($fiveStarBook)->create(['rating' => 5]);

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertViewHas('stats', fn (array $stats): bool => $stats['top_rated_books']->pluck('id')->all() === [
                $fiveStarBook->id,
                $fourStarBook->id,
            ]
            );
    }

    public function test_top_rated_books_are_limited_to_five(): void
    {
        $user = User::factory()->create();
        $books = Book::factory()->count(6)->create();
        $ratings = [5, 5, 4, 4, 3, 1];

        $books->each(fn (Book $book, int $index) => Review::factory()->for($user)->for($book)->create([
            'rating' => $ratings[$index],
        ])
        );

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertViewHas('stats', fn (array $stats): bool => $stats['top_rated_books']->count() === 5
                && ! $stats['top_rated_books']->pluck('id')->contains($books->last()->id)
            );
    }

    public function test_top_rated_books_display_five_when_user_has_five_distinct_reviewed_books(): void
    {
        $user = User::factory()->create();
        $books = Book::factory()->count(5)->create();

        $books->each(fn (Book $book, int $index) => Review::factory()->for($user)->for($book)->create([
            'rating' => ($index % 5) + 1,
        ])
        );

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertViewHas('stats', fn (array $stats): bool => $stats['top_rated_books']->count() === 5
                && $stats['top_rated_books']->pluck('id')->unique()->count() === 5
            );
    }

    public function test_top_rated_books_use_all_reviews_to_calculate_each_books_average(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        Review::factory()->for($user)->for($book)->create([
            'rating' => 4,
            'created_at' => now()->subDay(),
        ]);
        Review::factory()->for($user)->for($book)->create([
            'rating' => 5,
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertViewHas('stats', fn (array $stats): bool => $stats['top_rated_books']->count() === 1
                && $stats['top_rated_books']->first()['id'] === $book->id
                && $stats['top_rated_books']->first()['rating'] === 4.5
            );
    }

    public function test_top_rated_books_include_book_when_all_review_average_is_below_four(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        Review::factory()->for($user)->for($book)->create([
            'rating' => 1,
            'created_at' => now()->subDay(),
        ]);
        Review::factory()->for($user)->for($book)->create([
            'rating' => 5,
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertViewHas('stats', fn (array $stats): bool => $stats['top_rated_books']->count() === 1
                && $stats['top_rated_books']->first()['id'] === $book->id
                && $stats['top_rated_books']->first()['rating'] === 3.0
            )
            ->assertDontSee('レビューした書籍がありません');
    }

    public function test_top_rated_books_exclude_other_users_reviews(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownBook = Book::factory()->create();
        $otherBook = Book::factory()->create();
        Review::factory()->for($user)->for($ownBook)->create(['rating' => 4]);
        Review::factory()->for($otherUser)->for($otherBook)->create(['rating' => 5]);

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertViewHas('stats', fn (array $stats): bool => $stats['top_rated_books']->pluck('id')->all() === [$ownBook->id]
            );
    }

    public function test_top_rated_books_use_review_date_then_review_id_for_ties(): void
    {
        $user = User::factory()->create();
        $oldBook = Book::factory()->create();
        $firstSameTimeBook = Book::factory()->create();
        $lastSameTimeBook = Book::factory()->create();
        $sameTime = now();

        Review::factory()->for($user)->for($oldBook)->create([
            'rating' => 5,
            'created_at' => now()->subDay(),
        ]);
        $firstSameTimeReview = Review::factory()->for($user)->for($firstSameTimeBook)->create([
            'rating' => 5,
            'created_at' => $sameTime,
        ]);
        $lastSameTimeReview = Review::factory()->for($user)->for($lastSameTimeBook)->create([
            'rating' => 5,
            'created_at' => $sameTime,
        ]);

        $this->assertGreaterThan($firstSameTimeReview->id, $lastSameTimeReview->id);

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertViewHas('stats', fn (array $stats): bool => $stats['top_rated_books']->pluck('id')->all() === [
                $lastSameTimeBook->id,
                $firstSameTimeBook->id,
                $oldBook->id,
            ]
            );
    }

    public function test_top_rated_books_display_links_titles_authors_and_ratings(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create([
            'title' => 'リンク対象書籍',
            'author' => '表示対象著者',
        ]);
        Review::factory()->for($user)->for($book)->create(['rating' => 5]);

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('リンク対象書籍')
            ->assertSee('表示対象著者')
            ->assertSee('★★★★★')
            ->assertSee(route('books.show', $book), false)
            ->assertDontSee('レビューした書籍がありません');
    }

    public function test_genre_ratings_calculate_average_and_review_count(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $firstBook = Book::factory()->create();
        $secondBook = Book::factory()->create();
        $firstBook->genres()->attach($genre);
        $secondBook->genres()->attach($genre);
        Review::factory()->for($user)->for($firstBook)->create(['rating' => 4]);
        Review::factory()->for($user)->for($secondBook)->create(['rating' => 5]);

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertViewHas('stats', fn (array $stats): bool => $stats['genre_ratings']->first() === [
                'id' => $genre->id,
                'name' => $genre->name,
                'average_rating' => 4.5,
                'count' => 2,
            ]
            );
    }

    public function test_genre_ratings_are_ordered_by_average_rating_descending(): void
    {
        $user = User::factory()->create();
        $lowerGenre = Genre::factory()->create();
        $higherGenre = Genre::factory()->create();
        $lowerBook = Book::factory()->create();
        $higherBook = Book::factory()->create();
        $lowerBook->genres()->attach($lowerGenre);
        $higherBook->genres()->attach($higherGenre);
        Review::factory()->for($user)->for($lowerBook)->create(['rating' => 3]);
        Review::factory()->for($user)->for($higherBook)->create(['rating' => 5]);

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertViewHas('stats', fn (array $stats): bool => $stats['genre_ratings']->pluck('id')->all() === [
                $higherGenre->id,
                $lowerGenre->id,
            ]
            );
    }

    public function test_genre_ratings_are_limited_to_five(): void
    {
        $user = User::factory()->create();

        Genre::factory()->count(6)->create()->each(function (Genre $genre) use ($user): void {
            $book = Book::factory()->create();
            $book->genres()->attach($genre);
            Review::factory()->for($user)->for($book)->create(['rating' => 5]);
        });

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertViewHas('stats', fn (array $stats): bool => $stats['genre_ratings']->count() === 5
            );
    }

    public function test_review_of_multi_genre_book_is_counted_once_for_each_genre(): void
    {
        $user = User::factory()->create();
        $genres = Genre::factory()->count(2)->create();
        $book = Book::factory()->create();
        $book->genres()->attach($genres);
        Review::factory()->for($user)->for($book)->create(['rating' => 4]);

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertViewHas('stats', fn (array $stats): bool => $stats['genre_ratings']->pluck('id')->sort()->values()->all() === $genres->pluck('id')->sort()->values()->all()
                && $stats['genre_ratings']->every(fn (array $genre): bool => $genre['average_rating'] === 4.0 && $genre['count'] === 1
                )
            );
    }

    public function test_genre_ratings_exclude_other_users_reviews(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownGenre = Genre::factory()->create();
        $otherGenre = Genre::factory()->create();
        $ownBook = Book::factory()->create();
        $otherBook = Book::factory()->create();
        $ownBook->genres()->attach($ownGenre);
        $otherBook->genres()->attach($otherGenre);
        Review::factory()->for($user)->for($ownBook)->create(['rating' => 4]);
        Review::factory()->for($otherUser)->for($otherBook)->create(['rating' => 5]);

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertViewHas('stats', fn (array $stats): bool => $stats['genre_ratings']->pluck('id')->all() === [$ownGenre->id]
            );
    }

    public function test_genre_rating_ties_use_review_count_then_genre_id(): void
    {
        $user = User::factory()->create();
        $lowerIdGenre = Genre::factory()->create();
        $higherIdGenre = Genre::factory()->create();
        $moreReviewsGenre = Genre::factory()->create();

        foreach ([$lowerIdGenre, $higherIdGenre] as $genre) {
            $book = Book::factory()->create();
            $book->genres()->attach($genre);
            Review::factory()->for($user)->for($book)->create(['rating' => 4]);
        }

        foreach ([3, 5] as $rating) {
            $book = Book::factory()->create();
            $book->genres()->attach($moreReviewsGenre);
            Review::factory()->for($user)->for($book)->create(['rating' => $rating]);
        }

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertViewHas('stats', fn (array $stats): bool => $stats['genre_ratings']->pluck('id')->all() === [
                $moreReviewsGenre->id,
                $lowerIdGenre->id,
                $higherIdGenre->id,
            ]
            );
    }

    public function test_genre_ratings_display_details_and_genre_link(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create(['name' => '表示対象ジャンル']);
        $book = Book::factory()->create();
        $book->genres()->attach($genre);
        Review::factory()->for($user)->for($book)->create(['rating' => 5]);

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('表示対象ジャンル')
            ->assertSee('1件のレビュー')
            ->assertSee('5.0')
            ->assertSee(route('genres.show', $genre), false)
            ->assertDontSee('ジャンルが設定された書籍のレビューがありません');
    }

    public function test_genres_are_eager_loaded_in_one_query(): void
    {
        $user = User::factory()->create();
        $genres = Genre::factory()->count(3)->create();

        $genres->each(function (Genre $genre) use ($user): void {
            $book = Book::factory()->create();
            $book->genres()->attach($genre);
            Review::factory()->for($user)->for($book)->create();
        });

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)->get(route('reports.index'))->assertOk();

        $genreQueries = collect(DB::getQueryLog())->filter(function (array $query): bool {
            $sql = str_replace(['"', '`'], '', strtolower($query['query']));

            return str_contains($sql, 'genres')
                && str_contains($sql, 'book_genre');
        });

        $this->assertCount(1, $genreQueries);
    }
}
