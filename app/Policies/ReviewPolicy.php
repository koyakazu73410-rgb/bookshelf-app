<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    /**
     * Determine whether the authenticated user owns the review to update.
     *
     * @param  User  $user  The authenticated user.
     * @param  Review  $review  The review being updated.
     * @return bool True when the user owns the review.
     */
    public function update(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }

    /**
     * Determine whether the authenticated user owns the review to delete.
     *
     * @param  User  $user  The authenticated user.
     * @param  Review  $review  The review being deleted.
     * @return bool True when the user owns the review.
     */
    public function delete(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }
}
