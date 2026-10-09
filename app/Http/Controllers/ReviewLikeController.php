<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\RedirectResponse;

class ReviewLikeController extends Controller
{
    public function toggle(Review $review): RedirectResponse
    {
        if ($review->user_id === auth()->id()) {
            return back()->with('error', '自分のレビューにはいいねできません。');
        }

        $result = auth()->user()->likedReviews()->toggle($review->id);
        $added = count($result['attached']) > 0;

        return back()->with(
            'success',
            $added ? 'レビューにいいねしました。' : 'レビューのいいねを解除しました。'
        );
    }
}
