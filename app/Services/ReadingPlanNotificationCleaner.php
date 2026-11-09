<?php

namespace App\Services;

use App\Models\ReadingPlan;
use App\Notifications\ReadingPlanReminder;

class ReadingPlanNotificationCleaner
{
    /**
     * Delete reminder notifications owned by the plan's user for this plan.
     *
     * @param  ReadingPlan  $readingPlan  The plan whose reminder history is removed.
     * @return int The number of deleted notification records.
     */
    public function deleteFor(ReadingPlan $readingPlan): int
    {
        return $readingPlan->user
            ->notifications()
            ->where('type', ReadingPlanReminder::class)
            ->where('data->reading_plan_id', $readingPlan->getKey())
            ->delete();
    }
}
