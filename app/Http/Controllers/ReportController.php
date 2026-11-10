<?php

namespace App\Http\Controllers;

use App\Models\Genre;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReportController extends Controller
{
    private const TOP_RATED_BOOKS_LIMIT = 5;

    private const GENRE_RATINGS_LIMIT = 5;

    /**
     * Display the authenticated user's reading report.
     */
    public function index(Request $request): View
    {
        $reviews = $request->user()
            ->reviews()
            ->with([
                'book:id,title,author',
                'book.genres:id,name',
            ])
            ->get(['id', 'book_id', 'rating', 'created_at']);

        $ratingDistribution = collect(range(1, 5))
            ->map(fn (int $rating): int => $reviews->where('rating', $rating)->count());

        $stats = [
            'summary' => [
                'total_reviews' => $reviews->count(),
                'books_read' => $reviews->pluck('book_id')->unique()->count(),
                'average_rating' => $reviews->avg('rating') ?? 0,
            ],
            'rating_distribution' => $ratingDistribution,
            'top_rated_books' => $this->topRatedBooks($reviews),
            'genre_ratings' => $this->genreRatings($reviews),
        ];

        return view('reports.index', compact('stats'));
    }

    /**
     * Build the authenticated user's top-rated book list.
     *
     * Group all reviews by book, rank them by average rating, and use the
     * latest review date and review ID as tie-breakers.
     *
     * @param  Collection<int, Review>  $reviews
     * @return Collection<int, array{id: int, title: string, author: string, rating: float}>
     */
    private function topRatedBooks(Collection $reviews): Collection
    {
        return $reviews
            ->groupBy('book_id')
            ->map(function (Collection $bookReviews): array {
                $latestReview = $bookReviews
                    ->sort(fn (Review $left, Review $right): int => $this->compareReviewRecency($left, $right))
                    ->first();

                return [
                    'id' => $latestReview->book->id,
                    'title' => $latestReview->book->title,
                    'author' => $latestReview->book->author,
                    'rating' => (float) $bookReviews->avg('rating'),
                    'latest_reviewed_at' => $latestReview->created_at,
                    'latest_review_id' => $latestReview->id,
                ];
            })
            ->sort(function (array $left, array $right): int {
                return ($right['rating'] <=> $left['rating'])
                    ?: ($right['latest_reviewed_at'] <=> $left['latest_reviewed_at'])
                    ?: ($right['latest_review_id'] <=> $left['latest_review_id']);
            })
            ->take(self::TOP_RATED_BOOKS_LIMIT)
            ->map(fn (array $book): array => [
                'id' => $book['id'],
                'title' => $book['title'],
                'author' => $book['author'],
                'rating' => $book['rating'],
            ])
            ->values();
    }

    /**
     * Compare reviews by newest creation time, then by highest review ID.
     *
     * @param  Review  $left  The left-hand review.
     * @param  Review  $right  The right-hand review.
     * @return int A negative, zero, or positive comparison result.
     */
    private function compareReviewRecency(Review $left, Review $right): int
    {
        return ($right->created_at <=> $left->created_at)
            ?: ($right->id <=> $left->id);
    }

    /**
     * Aggregate the authenticated user's reviews by book genre.
     *
     * Reviews for books with multiple genres count once toward every genre.
     * Equal averages are ordered by review count, then by genre ID.
     *
     * @param  Collection<int, Review>  $reviews
     * @return Collection<int, array{id: int, name: string, average_rating: float, count: int}>
     */
    private function genreRatings(Collection $reviews): Collection
    {
        return $reviews
            ->flatMap(fn (Review $review): Collection => $review->book->genres->map(
                fn (Genre $genre): array => [
                    'genre' => $genre,
                    'rating' => $review->rating,
                ]
            ))
            ->groupBy(fn (array $item): int => $item['genre']->id)
            ->map(function (Collection $items): array {
                $genre = $items->first()['genre'];

                return [
                    'id' => $genre->id,
                    'name' => $genre->name,
                    'average_rating' => (float) $items->avg('rating'),
                    'count' => $items->count(),
                ];
            })
            ->sort(function (array $left, array $right): int {
                return ($right['average_rating'] <=> $left['average_rating'])
                    ?: ($right['count'] <=> $left['count'])
                    ?: ($left['id'] <=> $right['id']);
            })
            ->take(self::GENRE_RATINGS_LIMIT)
            ->values();
    }
}
