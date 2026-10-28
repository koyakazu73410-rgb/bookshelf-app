<?php

namespace App\Services;

use App\Models\ReadingPlan;
use App\Notifications\ReadingPlanReminder;
use Illuminate\Database\QueryException;

class ReadingPlanReminderNotifier
{
    /**
     * Send one reminder after the caller has selected an eligible plan.
     *
     * Date and status eligibility are the responsibility of the batch
     * command. The deterministic notification ID makes this operation
     * idempotent for a plan, target date and timing combination.
     */
    public function send(ReadingPlan $readingPlan, string $timing): bool
    {
        $readingPlan->loadMissing(['user', 'book']);
        $notification = new ReadingPlanReminder($readingPlan, $timing);

        if ($readingPlan->user->notifications()->whereKey($notification->id)->exists()) {
            return false;
        }

        try {
            $readingPlan->user->notify($notification);
        } catch (QueryException $exception) {
            // A concurrent process may have inserted the deterministic ID
            // after the existence check. Only suppress that duplicate case.
            if ($readingPlan->user->notifications()->whereKey($notification->id)->exists()) {
                return false;
            }

            throw $exception;
        }

        return true;
    }
}
