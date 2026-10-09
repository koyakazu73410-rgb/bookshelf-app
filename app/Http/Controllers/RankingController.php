<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\View\View;

class RankingController extends Controller
{
    public function index(): View
    {
        $rankedBooks = Book::with('genres')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->has('reviews')
            ->orderByDesc('reviews_avg_rating')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('ranking.index', compact('rankedBooks'));
    }
}
