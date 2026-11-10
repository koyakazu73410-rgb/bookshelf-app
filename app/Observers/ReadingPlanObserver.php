<?php

namespace App\Observers;

use App\Models\ReadingPlan;
use App\Services\ReadingPlanNotificationCleaner;

class ReadingPlanObserver
{
    public function __construct(
        private ReadingPlanNotificationCleaner $notificationCleaner
    ) {}

    /**
     * Delete all reminder history belonging to the reading plan before deletion.
     */
    public function deleting(ReadingPlan $readingPlan): void
    {
        $this->notificationCleaner->deleteFor($readingPlan);
    }
}
