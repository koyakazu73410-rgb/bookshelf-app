<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewRequest;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReviewController extends Controller
{
    /**
     * Store a review for the specified book.
     */
    public function store(ReviewRequest $request, Book $book): RedirectResponse
    {
        $validated = $request->validated();

        $validated['user_id'] = auth()->user()->id;

        $validated['book_id'] = $book->id;

        Review::create($validated);

        return redirect()
            ->route('books.show', $book)
            ->with('success', 'レビューを投稿しました。');
    }

    /**
     * Display the form for editing the specified review.
     */
    public function edit(Review $review): View
    {
        $this->authorize('update', $review);

        return view('reviews.edit', compact('review'));
    }

    /**
     * Update the specified review.
     */
    public function update(ReviewRequest $request, Review $review): RedirectResponse
    {
        $this->authorize('update', $review);

        $validated = $request->validated();

        $review->update($validated);

        return redirect()
            ->route('books.show', $review->book)
            ->with('success', 'レビューを更新しました。');
    }

    /**
     * Delete the specified review.
     */
    public function destroy(Review $review): RedirectResponse
    {
        $this->authorize('delete', $review);

        $book = $review->book;

        $review->delete();

        return redirect()
            ->route('books.show', $book)
            ->with('success', 'レビューを削除しました。');
    }
}
