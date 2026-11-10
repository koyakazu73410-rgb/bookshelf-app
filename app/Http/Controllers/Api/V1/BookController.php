<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexBookRequest;
use App\Http\Requests\Api\V1\StoreBookRequest;
use App\Http\Requests\Api\V1\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class BookController extends Controller
{
    /**
     * Return a filtered and paginated collection of books.
     */
    public function index(IndexBookRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $books = Book::query()
            ->with('genres')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->when($validated['keyword'] ?? null, function (Builder $query, string $keyword) {
                $query->where(function (Builder $query) use ($keyword) {
                    $query->where('title', 'like', "%{$keyword}%")
                        ->orWhere('author', 'like', "%{$keyword}%");
                });
            })
            ->when($validated['genre_id'] ?? null, function (Builder $query, int $genreId) {
                $query->whereHas('genres', fn (Builder $query) => $query->whereKey($genreId));
            })
            ->latest()
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        return BookResource::collection($books);
    }

    /**
     * Return the specified book with its related data.
     */
    public function show(Book $book): BookResource
    {
        $book->load(['genres', 'reviews.user'])
            ->loadAvg('reviews', 'rating')
            ->loadCount('reviews');

        return new BookResource($book);
    }

    /**
     * Store a book owned by the authenticated API user.
     */
    public function store(StoreBookRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $genres = $validated['genres'];
        unset($validated['genres']);
        $validated['user_id'] = $request->user()->id;

        $book = DB::transaction(function () use ($validated, $genres): Book {
            $book = Book::create($validated);
            $book->genres()->sync($genres);

            return $book;
        });

        $this->loadResourceRelations($book);

        return (new BookResource($book))->response()->setStatusCode(201);
    }

    /**
     * Update the specified book and synchronize its genres.
     */
    public function update(UpdateBookRequest $request, Book $book): BookResource
    {
        $validated = $request->validated();
        $genres = $validated['genres'];
        unset($validated['genres']);

        DB::transaction(function () use ($book, $validated, $genres): void {
            $book->update($validated);
            $book->genres()->sync($genres);
        });

        $this->loadResourceRelations($book);

        return new BookResource($book);
    }

    /**
     * Delete the specified book after policy authorization.
     */
    public function destroy(Book $book): JsonResponse
    {
        $this->authorize('delete', $book);
        DB::transaction(function () use ($book): void {
            $book->delete();
        });

        return response()->json(null, 204);
    }

    /**
     * Load relationships and aggregates used by the API resource.
     */
    private function loadResourceRelations(Book $book): void
    {
        $book->load('genres')
            ->loadAvg('reviews', 'rating')
            ->loadCount('reviews');
    }
}
