<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewLikeSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::whereIn('email', [
            'yamada@example.com',
            'suzuki@example.com',
            'tanaka@example.com',
            'sato@example.com',
            'takahashi@example.com',
        ])->orderBy('id')->get()->values();

        $reviews = Review::with('user')
            ->orderBy('book_id')
            ->orderBy('user_id')
            ->get();

        foreach ($reviews as $index => $review) {
            $likeCount = $index % 4;
            $candidateIds = $users
                ->where('id', '!=', $review->user_id)
                ->pluck('id')
                ->values();

            $likerIds = $likeCount === 0
                ? collect()
                : collect(range(0, $likeCount - 1))
                    ->map(fn (int $offset) => $candidateIds->get(($index + $offset) % $candidateIds->count()))
                    ->values();

            $review->likedUsers()->syncWithoutDetaching($likerIds);
        }
    }
}
