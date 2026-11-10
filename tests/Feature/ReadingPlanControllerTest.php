<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\ReadingPlanReminder;
use App\Services\ReadingPlanReminderNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReadingPlanControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_store_own_in_progress_plan(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $response = $this->actingAs($user)->post(route('reading-plans.store'), [
            'book_id' => $book->id,
            'target_date' => now()->addWeek()->toDateString(),
            'user_id' => User::factory()->create()->id,
        ]);

        $response->assertRedirect(route('reading-plans.index'))
            ->assertSessionHas('success', '読書計画を登録しました。');
        $this->assertDatabaseHas('reading_plans', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => ReadingPlanStatus::InProgress->value,
            'completed_at' => null,
        ]);
        $this->assertDatabaseCount('reading_plans', 1);
    }

    public function test_guest_is_redirected_from_every_reading_plan_route(): void
    {
        $readingPlan = ReadingPlan::factory()->create();

        $responses = [
            $this->get(route('reading-plans.index')),
            $this->get(route('reading-plans.create')),
            $this->post(route('reading-plans.store')),
            $this->get(route('reading-plans.edit', $readingPlan)),
            $this->put(route('reading-plans.update', $readingPlan)),
            $this->delete(route('reading-plans.destroy', $readingPlan)),
            $this->post(route('reading-plans.complete', $readingPlan)),
        ];

        foreach ($responses as $response) {
            $response->assertRedirect(route('login'));
        }
    }

    public function test_non_owner_cannot_update_delete_or_complete_and_database_is_unchanged(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        $readingPlan = ReadingPlan::factory()->create([
            'target_date' => '2026-11-01',
            'status' => ReadingPlanStatus::InProgress,
            'completed_at' => null,
        ]);
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->put(route('reading-plans.update', $readingPlan), [
                'target_date' => '2026-12-01',
            ])
            ->assertForbidden();
        $this->actingAs($otherUser)
            ->delete(route('reading-plans.destroy', $readingPlan))
            ->assertForbidden();
        $this->actingAs($otherUser)
            ->post(route('reading-plans.complete', $readingPlan))
            ->assertForbidden();

        $this->assertDatabaseHas('reading_plans', [
            'id' => $readingPlan->id,
            'status' => ReadingPlanStatus::InProgress,
            'completed_at' => null,
        ]);
        $this->assertSame('2026-11-01', $readingPlan->fresh()->target_date->toDateString());
    }

    public function test_owner_can_update_target_date(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        $owner = User::factory()->create();
        $readingPlan = ReadingPlan::factory()->for($owner)->create([
            'target_date' => '2026-11-01',
        ]);

        $this->actingAs($owner)
            ->put(route('reading-plans.update', $readingPlan), [
                'target_date' => '2026-12-01',
            ])
            ->assertRedirect(route('reading-plans.index'))
            ->assertSessionHas('success', '読書計画を更新しました。');

        $this->assertDatabaseHas('reading_plans', [
            'id' => $readingPlan->id,
            'status' => ReadingPlanStatus::InProgress,
        ]);
        $this->assertSame('2026-12-01', $readingPlan->fresh()->target_date->toDateString());
    }

    public function test_owner_cannot_update_completed_plan_by_direct_put_request(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        $owner = User::factory()->create();
        $readingPlan = ReadingPlan::factory()->for($owner)->create([
            'target_date' => '2026-11-01',
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => '2026-10-19 12:00:00',
        ]);

        $this->actingAs($owner)
            ->put(route('reading-plans.update', $readingPlan), [
                'target_date' => '2026-12-01',
            ])
            ->assertForbidden();

        $readingPlan->refresh();
        $this->assertSame('2026-11-01', $readingPlan->target_date->toDateString());
        $this->assertSame(ReadingPlanStatus::Completed, $readingPlan->status);
        $this->assertSame('2026-10-19 12:00:00', $readingPlan->completed_at->format('Y-m-d H:i:s'));
    }

    public function test_updating_expired_plan_returns_it_to_in_progress_and_clears_completed_at(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        $owner = User::factory()->create();
        $readingPlan = ReadingPlan::factory()->for($owner)->create([
            'target_date' => '2026-10-19',
            'status' => ReadingPlanStatus::Expired,
            'completed_at' => '2026-10-19 20:00:00',
        ]);

        $this->actingAs($owner)
            ->put(route('reading-plans.update', $readingPlan), [
                'target_date' => '2026-10-21',
            ])
            ->assertRedirect(route('reading-plans.index'));

        $this->assertDatabaseHas('reading_plans', [
            'id' => $readingPlan->id,
            'status' => ReadingPlanStatus::InProgress,
            'completed_at' => null,
        ]);
        $this->assertSame('2026-10-21', $readingPlan->fresh()->target_date->toDateString());
    }

    public function test_owner_can_complete_plan_and_completed_at_is_recorded(): void
    {
        Carbon::setTestNow('2026-10-20 15:30:45');
        $owner = User::factory()->create();
        $readingPlan = ReadingPlan::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->post(route('reading-plans.complete', $readingPlan))
            ->assertRedirect(route('reading-plans.index'))
            ->assertSessionHas('success', '読書計画を読了にしました。');

        $this->assertDatabaseHas('reading_plans', [
            'id' => $readingPlan->id,
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => '2026-10-20 15:30:45',
        ]);
    }

    public function test_owner_can_delete_plan(): void
    {
        $owner = User::factory()->create();
        $readingPlan = ReadingPlan::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->delete(route('reading-plans.destroy', $readingPlan))
            ->assertRedirect(route('reading-plans.index'))
            ->assertSessionHas('success', '読書計画を削除しました。');

        $this->assertDatabaseMissing('reading_plans', [
            'id' => $readingPlan->id,
        ]);
    }

    public function test_deleting_plan_removes_its_read_and_unread_notifications_only(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $readingPlan = ReadingPlan::factory()->for($owner)->create();
        $otherPlan = ReadingPlan::factory()->for($owner)->create();
        $notifier = app(ReadingPlanReminderNotifier::class);

        $notifier->send($readingPlan, ReadingPlanReminder::TIMING_THREE_DAYS_BEFORE);
        $notifier->send($readingPlan, ReadingPlanReminder::TIMING_ON_DUE_DATE);
        $notifier->send($otherPlan, ReadingPlanReminder::TIMING_ON_DUE_DATE);

        $readNotification = $owner->notifications()
            ->where('data->reading_plan_id', $readingPlan->id)
            ->firstOrFail();
        $readNotification->markAsRead();

        $otherUsersNotification = $otherUser->notifications()->create([
            'id' => fake()->uuid(),
            'type' => ReadingPlanReminder::class,
            'data' => [
                'reading_plan_id' => $readingPlan->id,
                'title' => '別ユーザーの通知',
            ],
        ]);

        $this->actingAs($owner)
            ->delete(route('reading-plans.destroy', $readingPlan))
            ->assertRedirect(route('reading-plans.index'));

        $this->assertDatabaseMissing('reading_plans', ['id' => $readingPlan->id]);
        $this->assertSame(0, $owner->notifications()
            ->where('data->reading_plan_id', $readingPlan->id)
            ->count());
        $this->assertDatabaseHas('notifications', ['id' => $otherUsersNotification->id]);
        $this->assertSame(1, $owner->notifications()
            ->where('data->reading_plan_id', $otherPlan->id)
            ->count());
    }

    public function test_failed_non_owner_delete_keeps_plan_notifications(): void
    {
        $readingPlan = ReadingPlan::factory()->create();
        $otherUser = User::factory()->create();

        app(ReadingPlanReminderNotifier::class)->send(
            $readingPlan,
            ReadingPlanReminder::TIMING_ON_DUE_DATE
        );

        $this->actingAs($otherUser)
            ->delete(route('reading-plans.destroy', $readingPlan))
            ->assertForbidden();

        $this->assertDatabaseHas('reading_plans', ['id' => $readingPlan->id]);
        $this->assertSame(1, $readingPlan->user->notifications()
            ->where('data->reading_plan_id', $readingPlan->id)
            ->count());
    }

    public function test_updating_target_date_keeps_existing_notification_history(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        $owner = User::factory()->create();
        $readingPlan = ReadingPlan::factory()->for($owner)->create([
            'target_date' => '2026-11-01',
        ]);

        app(ReadingPlanReminderNotifier::class)->send(
            $readingPlan,
            ReadingPlanReminder::TIMING_THREE_DAYS_BEFORE
        );
        $notificationId = $owner->notifications()->firstOrFail()->id;

        $this->actingAs($owner)
            ->put(route('reading-plans.update', $readingPlan), [
                'target_date' => '2026-12-01',
            ])
            ->assertRedirect(route('reading-plans.index'));

        $this->assertDatabaseHas('notifications', ['id' => $notificationId]);
        $this->assertSame('2026-11-01', $owner->notifications()
            ->findOrFail($notificationId)
            ->data['target_date']);
    }
}
