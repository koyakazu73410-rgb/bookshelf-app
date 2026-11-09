<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\BookSeeder;
use Database\Seeders\GenreSeeder;
use Database\Seeders\ReadingPlanSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ReadingPlanSeederTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_TODAY = '2026-11-01 10:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::TEST_TODAY, 'Asia/Tokyo'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_creates_six_required_reading_plan_scenarios(): void
    {
        $this->seedPrerequisites();

        $this->seed(ReadingPlanSeeder::class);

        $this->assertDatabaseCount('reading_plans', 6);
        foreach (['2026-11-04', '2026-11-01', '2026-10-29', '2026-11-08'] as $date) {
            $this->assertTrue(
                ReadingPlan::query()
                    ->whereDate('target_date', $date)
                    ->where('status', ReadingPlanStatus::InProgress->value)
                    ->exists()
            );
        }
    }

    public function test_it_assigns_five_plans_to_yamada_and_one_to_suzuki_using_distinct_books(): void
    {
        $this->seedPrerequisites();
        $this->seed(ReadingPlanSeeder::class);

        $yamada = User::where('email', 'yamada@example.com')->firstOrFail();
        $suzuki = User::where('email', 'suzuki@example.com')->firstOrFail();

        $this->assertSame(5, $yamada->readingPlans()->count());
        $this->assertSame(1, $suzuki->readingPlans()->count());
        $this->assertSame(6, ReadingPlan::query()->distinct()->count('book_id'));
    }

    public function test_it_sets_status_and_completion_date_from_requirements(): void
    {
        $this->seedPrerequisites();
        $this->seed(ReadingPlanSeeder::class);

        $completedPlan = ReadingPlan::query()
            ->where('status', ReadingPlanStatus::Completed->value)
            ->firstOrFail();

        $this->assertSame('2026-10-22', $completedPlan->target_date->toDateString());
        $this->assertSame('2026-10-27', $completedPlan->completed_at->toDateString());
        $this->assertSame(5, ReadingPlan::where('status', ReadingPlanStatus::InProgress->value)->count());
        $this->assertSame(0, ReadingPlan::where('status', ReadingPlanStatus::Expired->value)->count());
    }

    public function test_rerunning_seeder_does_not_create_duplicates(): void
    {
        $this->seedPrerequisites();

        $this->seed(ReadingPlanSeeder::class);
        $ids = ReadingPlan::query()->orderBy('id')->pluck('id');
        $this->seed(ReadingPlanSeeder::class);

        $this->assertDatabaseCount('reading_plans', 6);
        $this->assertSame($ids->all(), ReadingPlan::query()->orderBy('id')->pluck('id')->all());
    }

    public function test_missing_prerequisite_rolls_back_without_creating_any_plan(): void
    {
        $this->seedPrerequisites();
        Book::where('isbn', '9784478025819')->delete();

        try {
            $this->seed(ReadingPlanSeeder::class);
            $this->fail('ReadingPlanSeeder should stop when a required book is missing.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('prerequisites are missing', $exception->getMessage());
        }

        $this->assertDatabaseCount('reading_plans', 0);
    }

    public function test_existing_manual_plan_is_preserved_and_transaction_is_rolled_back(): void
    {
        $this->seedPrerequisites();
        $user = User::where('email', 'suzuki@example.com')->firstOrFail();
        $book = Book::where('isbn', '9784478025819')->firstOrFail();

        $manualPlan = new ReadingPlan;
        $manualPlan->user()->associate($user);
        $manualPlan->book()->associate($book);
        $manualPlan->target_date = Carbon::today('Asia/Tokyo')->addMonth();
        $manualPlan->status = ReadingPlanStatus::InProgress;
        $manualPlan->save();

        try {
            $this->seed(ReadingPlanSeeder::class);
            $this->fail('ReadingPlanSeeder should not overwrite a manual plan.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('stopped to protect an existing plan', $exception->getMessage());
        }

        $this->assertDatabaseCount('reading_plans', 1);
        $manualPlan->refresh();
        $this->assertSame('2026-12-01', $manualPlan->target_date->toDateString());
        $this->assertSame(ReadingPlanStatus::InProgress, $manualPlan->status);
    }

    public function test_seeded_scenarios_drive_expiration_and_three_reminder_targets(): void
    {
        $this->seedPrerequisites();
        $this->seed(ReadingPlanSeeder::class);

        $this->artisan('reading-plans:expire')->assertSuccessful();
        $this->artisan('reading-plans:remind')->assertSuccessful();

        $this->assertTrue(
            ReadingPlan::query()
                ->whereDate('target_date', '2026-10-29')
                ->where('status', ReadingPlanStatus::Expired->value)
                ->exists()
        );
        $this->assertDatabaseCount('notifications', 3);

        $timings = User::where('email', 'yamada@example.com')
            ->firstOrFail()
            ->notifications
            ->map(fn ($notification): string => $notification->data['timing'])
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'on_due_date',
            'three_days_after',
            'three_days_before',
        ], $timings);
    }

    private function seedPrerequisites(): void
    {
        $this->seed(UserSeeder::class);
        $this->seed(GenreSeeder::class);
        $this->seed(BookSeeder::class);
    }
}
