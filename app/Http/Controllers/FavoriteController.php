<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Book;

class FavoriteController extends Controller
{
    public function toggle(Book $book)
    {
        $user = auth()->user();

        if ($user->favoriteBooks->contains($book->id)) {
            $user->favoriteBooks()->detach($book->id);
        return back()
        ->with('success', 'お気に入りを解除しました。');
        } else {
            $user->favoriteBooks()->syncWithoutDetaching([$book->id]);

        return back()
        ->with('success', 'お気に入りに追加しました。');
        }
    }

    public function index()
    {
        $user = auth()->user();

        $books = $user->favoriteBooks()->paginate(10);

        return view('favorites.index', compact('books'));
    }
}