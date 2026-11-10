<?php

namespace App\Console\Commands;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Notifications\ReadingPlanReminder;
use App\Services\ReadingPlanReminderNotifier;
use Illuminate\Console\Command;

class SendReadingPlanReminders extends Command
{
    /** @var string */
    protected $signature = 'reading-plans:remind';

    /** @var string */
    protected $description = '読書計画のリマインダー通知を生成します。';

    /** Execute the daily reminder delivery. */
    public function handle(ReadingPlanReminderNotifier $notifier): int
    {
        $today = now('Asia/Tokyo')->startOfDay();
        $sentCount = 0;

        $targets = [
            [
                'date' => $today->copy()->addDays(3)->toDateString(),
                'status' => ReadingPlanStatus::InProgress,
                'timing' => ReadingPlanReminder::TIMING_THREE_DAYS_BEFORE,
            ],
            [
                'date' => $today->toDateString(),
                'status' => ReadingPlanStatus::InProgress,
                'timing' => ReadingPlanReminder::TIMING_ON_DUE_DATE,
            ],
            [
                'date' => $today->copy()->subDays(3)->toDateString(),
                'status' => ReadingPlanStatus::Expired,
                'timing' => ReadingPlanReminder::TIMING_THREE_DAYS_AFTER,
            ],
        ];

        foreach ($targets as $target) {
            ReadingPlan::query()
                ->with(['user', 'book'])
                ->whereDate('target_date', $target['date'])
                ->where('status', $target['status']->value)
                ->chunkById(100, function ($readingPlans) use ($notifier, $target, &$sentCount): void {
                    foreach ($readingPlans as $readingPlan) {
                        if ($notifier->send($readingPlan, $target['timing'])) {
                            $sentCount++;
                        }
                    }
                });
        }

        $this->info("{$sentCount}件のリマインダー通知を生成しました。");

        return self::SUCCESS;
    }
}
