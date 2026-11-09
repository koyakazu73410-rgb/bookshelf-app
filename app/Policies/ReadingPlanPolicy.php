<?php

namespace App\Policies;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Models\User;

class ReadingPlanPolicy
{
    /**
     * Determine whether the authenticated user owns the plan to update.
     *
     * @param  User  $user  The authenticated user.
     * @param  ReadingPlan  $readingPlan  The reading plan being updated.
     * @return bool True when the user owns a non-completed plan.
     */
    public function update(User $user, ReadingPlan $readingPlan): bool
    {
        return $user->id === $readingPlan->user_id
            && $readingPlan->status !== ReadingPlanStatus::Completed;
    }

    /**
     * Determine whether the authenticated user owns the plan to delete.
     *
     * @param  User  $user  The authenticated user.
     * @param  ReadingPlan  $readingPlan  The reading plan being deleted.
     * @return bool True when the user owns the plan.
     */
    public function delete(User $user, ReadingPlan $readingPlan): bool
    {
        return $user->id === $readingPlan->user_id;
    }

    /**
     * Determine whether the authenticated user owns the plan to complete.
     *
     * @param  User  $user  The authenticated user.
     * @param  ReadingPlan  $readingPlan  The reading plan being completed.
     * @return bool True when the user owns the plan.
     */
    public function complete(User $user, ReadingPlan $readingPlan): bool
    {
        return $user->id === $readingPlan->user_id;
    }
}
