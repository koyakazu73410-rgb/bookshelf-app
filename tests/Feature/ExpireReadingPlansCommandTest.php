<?php

namespace Tests\Feature;

use App\Models\ReadingPlan;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExpireReadingPlansCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-21 20:00:00', 'Asia/Tokyo'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_overdue_in_progress_plan_is_changed_to_expired(): void
    {
        $readingPlan = ReadingPlan::factory()->create([
            'target_date' => '2026-10-20',
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $this->artisan('reading-plans:expire')
            ->expectsOutput('1件の読書計画を期限切れに更新しました。')
            ->assertSuccessful();

        $this->assertSame(ReadingPlan::STATUS_EXPIRED, $readingPlan->fresh()->status);
    }

    public function test_today_and_future_in_progress_plans_are_not_changed(): void
    {
        $todayPlan = ReadingPlan::factory()->create([
            'target_date' => '2026-10-21',
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);
        $futurePlan = ReadingPlan::factory()->create([
            'target_date' => '2026-10-22',
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $this->artisan('reading-plans:expire')
            ->expectsOutput('0件の読書計画を期限切れに更新しました。')
            ->assertSuccessful();

        $this->assertSame(ReadingPlan::STATUS_IN_PROGRESS, $todayPlan->fresh()->status);
        $this->assertSame(ReadingPlan::STATUS_IN_PROGRESS, $futurePlan->fresh()->status);
    }

    public function test_completed_and_already_expired_plans_are_not_changed(): void
    {
        $completedAt = '2026-10-19 12:00:00';
        $completedPlan = ReadingPlan::factory()->create([
            'target_date' => '2026-10-20',
            'status' => ReadingPlan::STATUS_COMPLETED,
            'completed_at' => $completedAt,
        ]);
        $expiredPlan = ReadingPlan::factory()->create([
            'target_date' => '2026-10-20',
            'status' => ReadingPlan::STATUS_EXPIRED,
        ]);

        $this->artisan('reading-plans:expire')
            ->expectsOutput('0件の読書計画を期限切れに更新しました。')
            ->assertSuccessful();

        $this->assertSame(ReadingPlan::STATUS_COMPLETED, $completedPlan->fresh()->status);
        $this->assertSame($completedAt, $completedPlan->fresh()->completed_at->format('Y-m-d H:i:s'));
        $this->assertSame(ReadingPlan::STATUS_EXPIRED, $expiredPlan->fresh()->status);
    }

    public function test_multiple_overdue_plans_are_updated_in_one_run(): void
    {
        $plans = ReadingPlan::factory()->count(3)->create([
            'target_date' => '2026-10-20',
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $this->artisan('reading-plans:expire')
            ->expectsOutput('3件の読書計画を期限切れに更新しました。')
            ->assertSuccessful();

        foreach ($plans as $readingPlan) {
            $this->assertSame(ReadingPlan::STATUS_EXPIRED, $readingPlan->fresh()->status);
        }
    }

    public function test_reexecution_does_not_change_already_processed_plans(): void
    {
        $readingPlan = ReadingPlan::factory()->create([
            'target_date' => '2026-10-20',
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $this->artisan('reading-plans:expire')
            ->expectsOutput('1件の読書計画を期限切れに更新しました。')
            ->assertSuccessful();
        $firstUpdatedAt = $readingPlan->fresh()->updated_at;

        Carbon::setTestNow(Carbon::parse('2026-10-21 20:01:00', 'Asia/Tokyo'));

        $this->artisan('reading-plans:expire')
            ->expectsOutput('0件の読書計画を期限切れに更新しました。')
            ->assertSuccessful();

        $readingPlan->refresh();
        $this->assertSame(ReadingPlan::STATUS_EXPIRED, $readingPlan->status);
        $this->assertTrue($readingPlan->updated_at->equalTo($firstUpdatedAt));
    }

    public function test_scheduler_runs_command_daily_at_twenty_in_tokyo_without_overlapping(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'reading-plans:expire'));

        $this->assertNotNull($event);
        $this->assertSame('0 20 * * *', $event->expression);
        $this->assertSame('Asia/Tokyo', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
    }
}
