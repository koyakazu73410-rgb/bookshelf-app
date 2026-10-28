<?php

namespace App\Notifications;

use App\Models\ReadingPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

class ReadingPlanReminder extends Notification
{
    use Queueable;

    public const TIMING_THREE_DAYS_BEFORE = 'three_days_before';

    public const TIMING_ON_DUE_DATE = 'on_due_date';

    public const TIMING_THREE_DAYS_AFTER = 'three_days_after';

    /** @var list<string> */
    public const TIMINGS = [
        self::TIMING_THREE_DAYS_BEFORE,
        self::TIMING_ON_DUE_DATE,
        self::TIMING_THREE_DAYS_AFTER,
    ];

    private int $readingPlanId;

    private string $bookTitle;

    private string $targetDate;

    /**
     * Create a database notification for one already-selected reading plan.
     */
    public function __construct(ReadingPlan $readingPlan, private string $timing)
    {
        if (! in_array($timing, self::TIMINGS, true)) {
            throw new InvalidArgumentException('通知タイミングが正しくありません。');
        }

        $readingPlan->loadMissing('book');

        $this->readingPlanId = $readingPlan->id;
        $this->bookTitle = $readingPlan->book->title;
        $this->targetDate = $readingPlan->target_date->format('Y-m-d');
        $this->id = Uuid::uuid5(
            Uuid::NAMESPACE_URL,
            "bookshelf:reading-plan:{$this->readingPlanId}:{$this->targetDate}:{$this->timing}"
        )->toString();
    }

    /**
     * Use Laravel's database notification channel.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Build data consumed by the existing notification list.
     *
     * @return array{reading_plan_id: int, book_title: string, target_date: string, timing: string, title: string, body: string}
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'reading_plan_id' => $this->readingPlanId,
            'book_title' => $this->bookTitle,
            'target_date' => $this->targetDate,
            'timing' => $this->timing,
            'title' => '読書計画のリマインダー',
            'body' => "『{$this->bookTitle}』の目標日は{$this->targetDate}です。",
        ];
    }
}
