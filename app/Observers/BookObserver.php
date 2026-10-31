<?php

namespace App\Observers;

use App\Models\Book;
use App\Models\ReadingPlan;
use App\Services\ReadingPlanNotificationCleaner;

class BookObserver
{
    public function __construct(
        private ReadingPlanNotificationCleaner $notificationCleaner
    ) {}

    /**
     * Delete reminder history before the database cascades reading plans.
     */
    public function deleting(Book $book): void
    {
        $book->readingPlans()
            ->with('user')
            ->get()
            ->each(function (ReadingPlan $readingPlan): void {
                $this->notificationCleaner->deleteFor($readingPlan);
            });
    }
}
