<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchBookByIsbnRequest;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use App\Services\GoogleBooksService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use LogicException;

class BookController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        foreach (['keyword', 'genre', 'sort'] as $parameter) {
            abort_if($request->has($parameter) && is_array($request->input($parameter)), 400);
        }

        $query = Book::with('genres')
            ->withAvg('reviews', 'rating');

        if ($request->filled('keyword')) {
            $keyword = $request->string('keyword')->toString();

            $query->where(function ($query) use ($keyword): void {
                $query->where('title', 'like', '%'.$keyword.'%')
                    ->orWhere('author', 'like', '%'.$keyword.'%');
            });
        }

        if ($request->filled('genre')) {
            $query->whereHas('genres', function ($query) use ($request): void {
                $query->whereKey($request->input('genre'));
            });
        }

        match ($request->input('sort', 'latest')) {
            'oldest' => $query->oldest(),
            'title' => $query->orderBy('title'),
            'rating' => $query
                ->orderByRaw('reviews_avg_rating IS NULL')
                ->orderByDesc('reviews_avg_rating'),
            default => $query->latest(),
        };

        $books = $query->paginate(10)->withQueryString();
        $genres = Genre::orderBy('name')->get();

        return view('books.index', compact('books', 'genres'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $genres = Genre::all();

        return view('books.create', compact('genres'));
    }

    public function searchByIsbn(
        SearchBookByIsbnRequest $request,
        GoogleBooksService $googleBooks
    ): JsonResponse {
        try {
            $book = $googleBooks->findByIsbn($request->validated('isbn'));
        } catch (LogicException) {
            return response()->json([
                'error' => 'Google Books APIを利用できません。しばらくしてから再度お試しください。',
            ], 429);
        } catch (RequestException $exception) {
            if ($this->isGoogleBooksRateLimit($exception)) {
                return response()->json([
                    'error' => 'Google Books APIの利用上限に達しました。しばらくしてから再度お試しください。',
                ], 429);
            }

            return response()->json([
                'error' => 'Google Books APIから書籍情報を取得できませんでした。手動で入力してください。',
            ], 500);
        } catch (ConnectionException) {
            return response()->json([
                'error' => 'Google Books APIから書籍情報を取得できませんでした。手動で入力してください。',
            ], 500);
        }

        if ($book === null) {
            return response()->json([
                'error' => '書籍情報が見つかりませんでした。手動で入力してください。',
            ], 404);
        }

        $publishedDate = $book['publishedDate'] ?? null;

        return response()->json([
            'title' => $book['title'] ?? '',
            'author' => implode('、', $book['authors'] ?? []),
            'isbn' => $request->validated('isbn'),
            'published_date' => is_string($publishedDate)
                && preg_match('/^\d{4}-\d{2}-\d{2}$/', $publishedDate)
                    ? $publishedDate
                    : '',
            'description' => $book['description'] ?? '',
            'image_url' => $book['imageLinks']['thumbnail']
                ?? $book['imageLinks']['smallThumbnail']
                ?? '',
        ]);
    }

    /**
     * Determine whether Google rejected the request because of a usage limit.
     */
    private function isGoogleBooksRateLimit(RequestException $exception): bool
    {
        if ($exception->response->status() === 429) {
            return true;
        }

        $quotaReasons = [
            'rateLimitExceeded',
            'userRateLimitExceeded',
            'dailyLimitExceeded',
            'quotaExceeded',
        ];

        $reasons = collect($exception->response->json('error.errors', []))
            ->pluck('reason');

        return $reasons->intersect($quotaReasons)->isNotEmpty();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBookRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $genres = $validated['genres'];

        unset($validated['genres']);

        $validated['user_id'] = auth()->id();

        $book = DB::transaction(function () use ($validated, $genres): Book {
            $book = Book::create($validated);
            $book->genres()->attach($genres);

            return $book;
        });

        return redirect()->route('books.show', $book)
            ->with('success', '書籍を登録しました。');
    }

    /**
     * Display the specified resource.
     */
    public function show(Book $book): View
    {
        $book->load([
            'genres',
            'reviews' => function ($query): void {
                $query->with('user')
                    ->withCount('likedUsers')
                    ->latest();

                if (auth()->check()) {
                    $query->withExists([
                        'likedUsers as liked_by_current_user' => fn ($likeQuery) => $likeQuery->whereKey(auth()->id()),
                    ]);
                }
            },
        ]);

        $isFavorited = auth()->check()
            && auth()->user()->favoriteBooks()->whereKey($book->id)->exists();

        return view('books.show', compact('book', 'isFavorited'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id): View
    {
        $book = Book::findOrFail($id);

        $this->authorize('update', $book);

        $genres = Genre::all();

        return view('books.edit', compact('book', 'genres'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBookRequest $request, string $id): RedirectResponse
    {
        $book = Book::findOrFail($id);

        $this->authorize('update', $book);

        $validated = $request->validated();

        $genres = $validated['genres'];

        unset($validated['genres']);

        DB::transaction(function () use ($book, $validated, $genres): void {
            $book->update($validated);
            $book->genres()->sync($genres);
        });

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍を更新しました。');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): RedirectResponse
    {
        $book = Book::findOrFail($id);

        $this->authorize('delete', $book);

        $book->delete();

        return redirect()
            ->route('books.index')
            ->with('success', '書籍を削除しました。');
    }
}
