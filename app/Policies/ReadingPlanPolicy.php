<?php

namespace App\Policies;

use App\Models\ReadingPlan;
use App\Models\User;

class ReadingPlanPolicy
{
    /**
     * Determine whether the authenticated user owns the plan to update.
     */
    public function update(User $user, ReadingPlan $readingPlan): bool
    {
        return $user->id === $readingPlan->user_id
            && $readingPlan->status !== ReadingPlan::STATUS_COMPLETED;
    }

    /**
     * Determine whether the authenticated user owns the plan to delete.
     */
    public function delete(User $user, ReadingPlan $readingPlan): bool
    {
        return $user->id === $readingPlan->user_id;
    }

    /**
     * Determine whether the authenticated user owns the plan to complete.
     */
    public function complete(User $user, ReadingPlan $readingPlan): bool
    {
        return $user->id === $readingPlan->user_id;
    }
}
