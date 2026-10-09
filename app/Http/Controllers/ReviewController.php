<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewRequest;
use App\Models\Book;
use App\Models\Review;

class ReviewController extends Controller
{
    public function store(ReviewRequest $request, Book $book)
    {
        $validated = $request->validated();

        $validated['user_id'] = auth()->user()->id;

        $validated['book_id'] = $book->id;

        Review::create($validated);
        return redirect()
        ->route('books.show', $book)
        ->with('success', 'レビューを投稿しました。');
    }

    public function edit(Review $review)
    {
        $this->authorize('update', $review);

        return view('reviews.edit', compact('review'));
    }

    public function update(ReviewRequest $request, Review $review)
    {
        $this->authorize('update', $review);

        $validated = $request->validated();

        $review->update($validated);

        return redirect()
        ->route('books.show', $review->book)
        ->with('success', 'レビューを更新しました。');
    }

    public function destroy(Review $review)
    {
        $this->authorize('delete', $review);

        $book = $review->book;

        $review->delete();

        return redirect()
        ->route('books.show', $book)
        ->with('success', 'レビューを削除しました。');
    }
}
