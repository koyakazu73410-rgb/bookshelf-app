<?php

namespace Tests\Feature;

use App\Models\ReadingPlan;
use App\Notifications\ReadingPlanReminder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SendReadingPlanRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-10 20:00:00', 'Asia/Tokyo'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_command_sends_three_days_before_on_due_date_and_three_days_after_notifications(): void
    {
        $threeDaysBefore = ReadingPlan::factory()->create([
            'target_date' => '2026-11-13',
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);
        $onDueDate = ReadingPlan::factory()->create([
            'target_date' => '2026-11-10',
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);
        $threeDaysAfter = ReadingPlan::factory()->create([
            'target_date' => '2026-11-07',
            'status' => ReadingPlan::STATUS_EXPIRED,
        ]);

        $this->artisan('reading-plans:remind')
            ->expectsOutput('3件のリマインダー通知を生成しました。')
            ->assertSuccessful();

        $notifications = collect([
            $threeDaysBefore,
            $onDueDate,
            $threeDaysAfter,
        ])->mapWithKeys(fn (ReadingPlan $plan): array => [
            $plan->id => $plan->user->fresh()->notifications->first()->data,
        ]);

        $this->assertSame(
            ReadingPlanReminder::TIMING_THREE_DAYS_BEFORE,
            $notifications[$threeDaysBefore->id]['timing']
        );
        $this->assertSame(
            ReadingPlanReminder::TIMING_ON_DUE_DATE,
            $notifications[$onDueDate->id]['timing']
        );
        $this->assertSame(
            ReadingPlanReminder::TIMING_THREE_DAYS_AFTER,
            $notifications[$threeDaysAfter->id]['timing']
        );
    }

    public function test_command_excludes_completed_and_status_mismatched_plans(): void
    {
        foreach (['2026-11-13', '2026-11-10', '2026-11-07'] as $targetDate) {
            ReadingPlan::factory()->create([
                'target_date' => $targetDate,
                'status' => ReadingPlan::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        }

        ReadingPlan::factory()->create([
            'target_date' => '2026-11-13',
            'status' => ReadingPlan::STATUS_EXPIRED,
        ]);
        ReadingPlan::factory()->create([
            'target_date' => '2026-11-10',
            'status' => ReadingPlan::STATUS_EXPIRED,
        ]);
        ReadingPlan::factory()->create([
            'target_date' => '2026-11-07',
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);
        ReadingPlan::factory()->create([
            'target_date' => '2026-11-14',
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $this->artisan('reading-plans:remind')
            ->expectsOutput('0件のリマインダー通知を生成しました。')
            ->assertSuccessful();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_command_reexecution_does_not_duplicate_notifications(): void
    {
        ReadingPlan::factory()->create([
            'target_date' => '2026-11-10',
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $this->artisan('reading-plans:remind')
            ->expectsOutput('1件のリマインダー通知を生成しました。')
            ->assertSuccessful();
        $this->artisan('reading-plans:remind')
            ->expectsOutput('0件のリマインダー通知を生成しました。')
            ->assertSuccessful();

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_expiration_command_runs_before_reminder_flow_for_overdue_plan(): void
    {
        $readingPlan = ReadingPlan::factory()->create([
            'target_date' => '2026-11-07',
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $this->artisan('reading-plans:expire')->assertSuccessful();
        $this->artisan('reading-plans:remind')
            ->expectsOutput('1件のリマインダー通知を生成しました。')
            ->assertSuccessful();

        $this->assertSame(ReadingPlan::STATUS_EXPIRED, $readingPlan->fresh()->status);
        $this->assertSame(
            ReadingPlanReminder::TIMING_THREE_DAYS_AFTER,
            $readingPlan->user->fresh()->notifications->first()->data['timing']
        );
    }

    public function test_scheduler_runs_expiration_then_reminder_daily_at_twenty_in_tokyo(): void
    {
        $events = collect($this->app->make(Schedule::class)->events());
        $expireIndex = $events->search(
            fn ($event): bool => str_contains((string) $event->command, 'reading-plans:expire')
        );
        $remindIndex = $events->search(
            fn ($event): bool => str_contains((string) $event->command, 'reading-plans:remind')
        );

        $this->assertIsInt($expireIndex);
        $this->assertIsInt($remindIndex);
        $this->assertTrue($expireIndex < $remindIndex);

        foreach ([$events[$expireIndex], $events[$remindIndex]] as $event) {
            $this->assertSame('0 20 * * *', $event->expression);
            $this->assertSame('Asia/Tokyo', $event->timezone);
            $this->assertTrue($event->withoutOverlapping);
        }
    }
}
