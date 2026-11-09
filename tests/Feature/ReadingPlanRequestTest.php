<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Http\Requests\StoreReadingPlanRequest;
use App\Http\Requests\UpdateReadingPlanRequest;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ReadingPlanRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth'])->post('/_testing/reading-plans', function (StoreReadingPlanRequest $request) {
            return response()->json($request->validated());
        })->name('testing.reading-plans.store');

        Route::middleware(['web', 'auth'])->put('/_testing/reading-plans/{reading_plan}', function (
            UpdateReadingPlanRequest $request,
            ReadingPlan $reading_plan
        ) {
            return response()->json($request->validated());
        })->name('testing.reading-plans.update');
    }

    public function test_store_accepts_today_and_a_future_target_date(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        $user = User::factory()->create();
        $todayBook = Book::factory()->create();
        $futureBook = Book::factory()->create();

        $this->actingAs($user)->postJson('/_testing/reading-plans', [
            'book_id' => $todayBook->id,
            'target_date' => '2026-10-20',
        ])->assertOk()->assertJsonPath('target_date', '2026-10-20');

        $this->actingAs($user)->postJson('/_testing/reading-plans', [
            'book_id' => $futureBook->id,
            'target_date' => '2026-10-21',
        ])->assertOk()->assertJsonPath('target_date', '2026-10-21');
    }

    public function test_store_rejects_a_past_target_date(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');

        $this->actingAs(User::factory()->create())
            ->postJson('/_testing/reading-plans', [
                'book_id' => Book::factory()->create()->id,
                'target_date' => '2026-10-19',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_date');
    }

    public function test_store_rejects_a_nonexistent_book(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/_testing/reading-plans', [
                'book_id' => 999999,
                'target_date' => now()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('book_id');
    }

    public function test_store_rejects_a_duplicate_in_progress_plan_for_the_same_user_and_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        ReadingPlan::factory()->for($user)->for($book)->create([
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $this->actingAs($user)
            ->postJson('/_testing/reading-plans', [
                'book_id' => $book->id,
                'target_date' => now()->addDay()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('book_id');
    }

    public function test_store_allows_another_user_to_plan_the_same_book(): void
    {
        $book = Book::factory()->create();
        ReadingPlan::factory()->for(User::factory()->create())->for($book)->create([
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson('/_testing/reading-plans', [
                'book_id' => $book->id,
                'target_date' => now()->addDay()->toDateString(),
            ])
            ->assertOk();
    }

    public function test_store_allows_registration_after_a_completed_or_expired_plan(): void
    {
        foreach ([ReadingPlanStatus::Completed, ReadingPlanStatus::Expired] as $status) {
            $user = User::factory()->create();
            $book = Book::factory()->create();
            ReadingPlan::factory()->for($user)->for($book)->create(['status' => $status]);

            $this->actingAs($user)
                ->postJson('/_testing/reading-plans', [
                    'book_id' => $book->id,
                    'target_date' => now()->addDay()->toDateString(),
                ])
                ->assertOk();
        }
    }

    public function test_store_rejects_status_input(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/_testing/reading-plans', [
                'book_id' => Book::factory()->create()->id,
                'target_date' => now()->addDay()->toDateString(),
                'status' => ReadingPlanStatus::Completed->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_update_accepts_owner_and_rejects_book_id_and_status_changes(): void
    {
        $owner = User::factory()->create();
        $readingPlan = ReadingPlan::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->putJson("/_testing/reading-plans/{$readingPlan->id}", [
                'target_date' => now()->addWeek()->toDateString(),
            ])
            ->assertOk();

        $this->actingAs($owner)
            ->putJson("/_testing/reading-plans/{$readingPlan->id}", [
                'target_date' => now()->addWeek()->toDateString(),
                'book_id' => Book::factory()->create()->id,
                'status' => ReadingPlanStatus::Completed->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['book_id', 'status']);
    }

    public function test_update_accepts_today_and_rejects_a_past_or_missing_target_date(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        $owner = User::factory()->create();
        $readingPlan = ReadingPlan::factory()->for($owner)->create([
            'target_date' => '2026-10-21',
        ]);

        $this->actingAs($owner)
            ->putJson("/_testing/reading-plans/{$readingPlan->id}", [
                'target_date' => '2026-10-20',
            ])
            ->assertOk();

        $this->actingAs($owner)
            ->putJson("/_testing/reading-plans/{$readingPlan->id}", [
                'target_date' => '2026-10-19',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_date');

        $this->actingAs($owner)
            ->putJson("/_testing/reading-plans/{$readingPlan->id}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_date');
    }

    public function test_update_rejects_a_non_owner(): void
    {
        $readingPlan = ReadingPlan::factory()->create();

        $this->actingAs(User::factory()->create())
            ->putJson("/_testing/reading-plans/{$readingPlan->id}", [
                'target_date' => now()->addWeek()->toDateString(),
            ])
            ->assertForbidden();
    }
}
