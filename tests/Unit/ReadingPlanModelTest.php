<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReadingPlanModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_reading_plan_belongs_to_user_and_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $readingPlan = ReadingPlan::factory()->for($user)->for($book)->create();

        $this->assertInstanceOf(BelongsTo::class, $readingPlan->user());
        $this->assertInstanceOf(BelongsTo::class, $readingPlan->book());
        $this->assertTrue($readingPlan->user->is($user));
        $this->assertTrue($readingPlan->book->is($book));
    }

    public function test_user_and_book_have_many_reading_plans(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $readingPlan = ReadingPlan::factory()->for($user)->for($book)->create();

        $this->assertInstanceOf(HasMany::class, $user->readingPlans());
        $this->assertInstanceOf(HasMany::class, $book->readingPlans());
        $this->assertTrue($user->readingPlans->contains($readingPlan));
        $this->assertTrue($book->readingPlans->contains($readingPlan));
    }

    public function test_date_attributes_are_cast_to_carbon_instances(): void
    {
        $readingPlan = ReadingPlan::factory()->create([
            'target_date' => '2026-12-31',
            'completed_at' => '2026-12-20 10:30:00',
        ]);

        $this->assertInstanceOf(Carbon::class, $readingPlan->target_date);
        $this->assertInstanceOf(Carbon::class, $readingPlan->completed_at);
        $this->assertSame('2026-12-31', $readingPlan->target_date->toDateString());
        $this->assertSame('2026-12-20 10:30:00', $readingPlan->completed_at->format('Y-m-d H:i:s'));
    }

    public function test_factory_creates_an_in_progress_plan_with_no_completion_date(): void
    {
        $readingPlan = ReadingPlan::factory()->create();

        $this->assertSame('in_progress', ReadingPlan::STATUS_IN_PROGRESS);
        $this->assertSame('completed', ReadingPlan::STATUS_COMPLETED);
        $this->assertSame('expired', ReadingPlan::STATUS_EXPIRED);
        $this->assertSame(ReadingPlan::STATUS_IN_PROGRESS, $readingPlan->status);
        $this->assertNull($readingPlan->completed_at);
    }

    public function test_user_id_status_and_completed_at_are_not_mass_assignable(): void
    {
        $readingPlan = new ReadingPlan();

        $this->assertTrue($readingPlan->isFillable('book_id'));
        $this->assertTrue($readingPlan->isFillable('target_date'));
        $this->assertFalse($readingPlan->isFillable('user_id'));
        $this->assertFalse($readingPlan->isFillable('status'));
        $this->assertFalse($readingPlan->isFillable('completed_at'));
    }
}
