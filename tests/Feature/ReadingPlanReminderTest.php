<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\ReadingPlanReminder;
use App\Services\ReadingPlanReminderNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ReadingPlanReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_uses_database_channel_and_contains_common_reminder_data(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['title' => '通知対象書籍']);
        $readingPlan = ReadingPlan::factory()->for($user)->for($book)->create([
            'target_date' => '2026-11-15',
        ]);
        $notification = new ReadingPlanReminder(
            $readingPlan,
            ReadingPlanReminder::TIMING_THREE_DAYS_BEFORE
        );

        $this->assertSame(['database'], $notification->via($user));
        $this->assertSame([
            'reading_plan_id' => $readingPlan->id,
            'book_title' => '通知対象書籍',
            'target_date' => '2026-11-15',
            'timing' => ReadingPlanReminder::TIMING_THREE_DAYS_BEFORE,
            'title' => '読書計画のリマインダー',
            'body' => '『通知対象書籍』の目標日は2026-11-15です。',
        ], $notification->toDatabase($user));
    }

    public function test_all_confirmed_timing_values_can_be_stored_as_database_notifications(): void
    {
        $user = User::factory()->create();
        $readingPlan = ReadingPlan::factory()->for($user)->create([
            'target_date' => '2026-11-15',
        ]);
        $notifier = app(ReadingPlanReminderNotifier::class);

        foreach (ReadingPlanReminder::TIMINGS as $timing) {
            $notifier->send($readingPlan, $timing);
        }

        $this->assertCount(3, $user->fresh()->notifications);
        $this->assertEqualsCanonicalizing(
            ReadingPlanReminder::TIMINGS,
            $user->fresh()->notifications->pluck('data.timing')->all()
        );
    }

    public function test_notification_rejects_an_unknown_timing(): void
    {
        $readingPlan = ReadingPlan::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('通知タイミングが正しくありません。');

        new ReadingPlanReminder($readingPlan, 'unknown');
    }

    public function test_generated_notification_is_compatible_with_existing_notification_list(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['title' => '一覧表示対象書籍']);
        $readingPlan = ReadingPlan::factory()->for($user)->for($book)->create([
            'target_date' => '2026-11-15',
        ]);

        app(ReadingPlanReminderNotifier::class)->send(
            $readingPlan,
            ReadingPlanReminder::TIMING_ON_DUE_DATE
        );

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('読書計画のリマインダー')
            ->assertSee('一覧表示対象書籍')
            ->assertSee('既読にする');

        $this->assertSame(1, $user->fresh()->unreadNotifications()->count());
    }

    public function test_notifier_does_not_send_same_plan_date_and_timing_twice(): void
    {
        $readingPlan = ReadingPlan::factory()->create([
            'target_date' => '2026-11-15',
        ]);
        $notifier = app(ReadingPlanReminderNotifier::class);

        $this->assertTrue($notifier->send(
            $readingPlan,
            ReadingPlanReminder::TIMING_ON_DUE_DATE
        ));
        $this->assertFalse($notifier->send(
            $readingPlan,
            ReadingPlanReminder::TIMING_ON_DUE_DATE
        ));

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_same_plan_date_and_timing_generate_same_deterministic_notification_id(): void
    {
        $readingPlan = ReadingPlan::factory()->create([
            'target_date' => '2026-11-15',
        ]);

        $first = new ReadingPlanReminder(
            $readingPlan,
            ReadingPlanReminder::TIMING_ON_DUE_DATE
        );
        $second = new ReadingPlanReminder(
            $readingPlan->fresh(),
            ReadingPlanReminder::TIMING_ON_DUE_DATE
        );
        $differentTiming = new ReadingPlanReminder(
            $readingPlan,
            ReadingPlanReminder::TIMING_THREE_DAYS_BEFORE
        );

        $this->assertSame($first->id, $second->id);
        $this->assertNotSame($first->id, $differentTiming->id);
    }

    public function test_changed_target_date_creates_new_notification_but_returning_to_old_date_does_not_resend(): void
    {
        $readingPlan = ReadingPlan::factory()->create([
            'target_date' => '2026-11-15',
        ]);
        $notifier = app(ReadingPlanReminderNotifier::class);

        $this->assertTrue($notifier->send(
            $readingPlan,
            ReadingPlanReminder::TIMING_ON_DUE_DATE
        ));

        $readingPlan->target_date = '2026-11-20';
        $readingPlan->save();
        $this->assertTrue($notifier->send(
            $readingPlan->fresh(),
            ReadingPlanReminder::TIMING_ON_DUE_DATE
        ));

        $readingPlan->target_date = '2026-11-15';
        $readingPlan->save();
        $this->assertFalse($notifier->send(
            $readingPlan->fresh(),
            ReadingPlanReminder::TIMING_ON_DUE_DATE
        ));

        $this->assertDatabaseCount('notifications', 2);
        $this->assertEqualsCanonicalizing(
            ['2026-11-15', '2026-11-20'],
            $readingPlan->user->fresh()->notifications->pluck('data.target_date')->all()
        );
    }
}
