<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\ReadingPlanReminder;
use App\Services\ReadingPlanReminderNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class DeleteTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_book_deletion_removes_book_plan_and_notification(): void
    {
        [$owner, $book, $readingPlan, $notificationId] = $this->createPlanWithNotification();

        $this->actingAs($owner)
            ->delete(route('books.destroy', $book))
            ->assertRedirect(route('books.index'));

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        $this->assertDatabaseMissing('reading_plans', ['id' => $readingPlan->id]);
        $this->assertDatabaseMissing('notifications', ['id' => $notificationId]);
    }

    public function test_web_book_deletion_rolls_back_book_plan_and_notification_after_delete_failure(): void
    {
        [$owner, $book, $readingPlan, $notificationId] = $this->createPlanWithNotification();
        $this->throwAfterBookDeletion();

        $this->assertDeletionThrows(function () use ($owner, $book): void {
            $this->actingAs($owner)->delete(route('books.destroy', $book));
        });

        $this->assertDatabaseHas('books', ['id' => $book->id]);
        $this->assertDatabaseHas('reading_plans', ['id' => $readingPlan->id]);
        $this->assertDatabaseHas('notifications', ['id' => $notificationId]);
    }

    public function test_reading_plan_deletion_removes_plan_and_notification(): void
    {
        [$owner, $book, $readingPlan, $notificationId] = $this->createPlanWithNotification();

        $this->actingAs($owner)
            ->delete(route('reading-plans.destroy', $readingPlan))
            ->assertRedirect(route('reading-plans.index'));

        $this->assertDatabaseHas('books', ['id' => $book->id]);
        $this->assertDatabaseMissing('reading_plans', ['id' => $readingPlan->id]);
        $this->assertDatabaseMissing('notifications', ['id' => $notificationId]);
    }

    public function test_reading_plan_deletion_rolls_back_plan_and_notification_after_delete_failure(): void
    {
        [$owner, $book, $readingPlan, $notificationId] = $this->createPlanWithNotification();
        $this->throwAfterReadingPlanDeletion();

        $this->assertDeletionThrows(function () use ($owner, $readingPlan): void {
            $this->actingAs($owner)->delete(route('reading-plans.destroy', $readingPlan));
        });

        $this->assertDatabaseHas('books', ['id' => $book->id]);
        $this->assertDatabaseHas('reading_plans', ['id' => $readingPlan->id]);
        $this->assertDatabaseHas('notifications', ['id' => $notificationId]);
    }

    public function test_api_book_deletion_removes_book_plan_and_notification(): void
    {
        [$owner, $book, $readingPlan, $notificationId] = $this->createPlanWithNotification();
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/books/{$book->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        $this->assertDatabaseMissing('reading_plans', ['id' => $readingPlan->id]);
        $this->assertDatabaseMissing('notifications', ['id' => $notificationId]);
    }

    public function test_api_book_deletion_rolls_back_book_plan_and_notification_after_delete_failure(): void
    {
        [$owner, $book, $readingPlan, $notificationId] = $this->createPlanWithNotification();
        $this->throwAfterBookDeletion();
        Sanctum::actingAs($owner);

        $this->assertDeletionThrows(function () use ($book): void {
            $this->deleteJson("/api/v1/books/{$book->id}");
        });

        $this->assertDatabaseHas('books', ['id' => $book->id]);
        $this->assertDatabaseHas('reading_plans', ['id' => $readingPlan->id]);
        $this->assertDatabaseHas('notifications', ['id' => $notificationId]);
    }

    /**
     * @return array{User, Book, ReadingPlan, string}
     */
    private function createPlanWithNotification(): array
    {
        $owner = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id]);
        $readingPlan = ReadingPlan::factory()->create([
            'user_id' => $owner->id,
            'book_id' => $book->id,
        ]);

        app(ReadingPlanReminderNotifier::class)->send(
            $readingPlan,
            ReadingPlanReminder::TIMING_ON_DUE_DATE
        );

        $notificationId = $owner->notifications()->firstOrFail()->id;

        return [$owner, $book, $readingPlan, $notificationId];
    }

    private function throwAfterBookDeletion(): void
    {
        Book::deleted(function (): void {
            throw new RuntimeException('Forced failure after notification deletion.');
        });
    }

    private function throwAfterReadingPlanDeletion(): void
    {
        ReadingPlan::deleted(function (): void {
            throw new RuntimeException('Forced failure after notification deletion.');
        });
    }

    private function assertDeletionThrows(callable $delete): void
    {
        $this->withoutExceptionHandling();

        try {
            $delete();
            $this->fail('Expected the forced deletion failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Forced failure after notification deletion.',
                $exception->getMessage()
            );
        }
    }
}
