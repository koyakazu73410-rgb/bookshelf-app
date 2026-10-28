<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    /**
     * Add the book to, or remove it from, the authenticated user's favorites.
     */
    public function toggle(Book $book): RedirectResponse
    {
        $user = auth()->user();

        if ($user->favoriteBooks()->whereKey($book->id)->exists()) {
            $user->favoriteBooks()->detach($book->id);

            return back()
                ->with('success', 'お気に入りを解除しました。');
        } else {
            $user->favoriteBooks()->syncWithoutDetaching([$book->id]);

            return back()
                ->with('success', 'お気に入りに追加しました。');
        }
    }

    /**
     * Display the authenticated user's favorite books.
     */
    public function index(): View
    {
        $user = auth()->user();

        $books = $user->favoriteBooks()->paginate(10);

        return view('favorites.index', compact('books'));
    }
}
