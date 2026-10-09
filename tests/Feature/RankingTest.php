<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_ランキング画面が正常に表示される(): void
    {
        $response = $this->get(route('ranking.index'));

        $response->assertOk();
        $response->assertViewIs('ranking.index');
        $response->assertViewHas('rankedBooks');
    }

    public function test_平均評価が高い書籍ほど上位に表示されレビューのない書籍は除外される(): void
    {
        $user = User::factory()->create();
        $highRatedBook = Book::factory()->create(['title' => '高評価の書籍']);
        $lowRatedBook = Book::factory()->create(['title' => '低評価の書籍']);
        $bookWithoutReviews = Book::factory()->create(['title' => 'レビューなしの書籍']);

        Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $highRatedBook->id,
            'rating' => 5,
        ]);
        Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $highRatedBook->id,
            'rating' => 4,
        ]);
        Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $lowRatedBook->id,
            'rating' => 3,
        ]);

        $response = $this->get(route('ranking.index'));

        $response->assertOk();
        $response->assertSeeInOrder(['高評価の書籍', '低評価の書籍']);
        $response->assertDontSee('レビューなしの書籍');
        $response->assertViewHas('rankedBooks', function ($rankedBooks) use ($highRatedBook, $lowRatedBook, $bookWithoutReviews) {
            return $rankedBooks->pluck('id')->all() === [$highRatedBook->id, $lowRatedBook->id]
                && (float) $rankedBooks->first()->reviews_avg_rating === 4.5
                && ! $rankedBooks->contains('id', $bookWithoutReviews->id);
        });
    }

    public function test_ランキングに表示される書籍は最大10件である(): void
    {
        $user = User::factory()->create();

        $books = Book::factory()->count(11)->create();

        foreach ($books as $index => $book) {
            Review::factory()->create([
                'user_id' => $user->id,
                'book_id' => $book->id,
                'rating' => $index === 10 ? 1 : 5,
            ]);
        }

        $response = $this->get(route('ranking.index'));

        $response->assertOk();
        $response->assertViewHas('rankedBooks', function ($rankedBooks) use ($books) {
            return $rankedBooks->count() === 10
                && ! $rankedBooks->contains('id', $books->last()->id);
        });
    }
}
