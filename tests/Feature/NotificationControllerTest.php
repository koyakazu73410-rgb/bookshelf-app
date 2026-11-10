<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_notification_index(): void
    {
        $this->get(route('notifications.index'))
            ->assertRedirect(route('login'));
    }

    public function test_index_displays_only_authenticated_users_notifications_in_newest_first_order(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->createNotification($user, '古い自分の通知', '自分の本文', null, now()->subDay());
        $this->createNotification($user, '新しい自分の通知', '新しい本文', null, now());
        $this->createNotification($otherUser, '他ユーザーの通知', '表示禁止', null, now()->addMinute());

        $response = $this->actingAs($user)
            ->get(route('notifications.index'));

        $response->assertOk()
            ->assertSee('新しい自分の通知')
            ->assertSee('新しい本文')
            ->assertSee('古い自分の通知')
            ->assertSee('未読')
            ->assertSee('既読にする')
            ->assertDontSee('他ユーザーの通知')
            ->assertSeeInOrder(['新しい自分の通知', '古い自分の通知']);
    }

    public function test_authenticated_user_can_mark_own_notification_as_read(): void
    {
        $user = User::factory()->create();
        $id = $this->createNotification($user, '既読対象', '本文');

        $this->actingAs($user)
            ->post(route('notifications.read', $id))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('success', '通知を既読にしました。');

        $this->assertNotNull(DB::table('notifications')->where('id', $id)->value('read_at'));
    }

    public function test_marking_an_already_read_notification_is_idempotent(): void
    {
        Carbon::setTestNow('2026-11-03 10:00:00');

        try {
            $user = User::factory()->create();
            $id = $this->createNotification($user, '既読再操作対象', '本文');

            $this->actingAs($user)
                ->post(route('notifications.read', $id))
                ->assertRedirect(route('notifications.index'))
                ->assertSessionHas('success', '通知を既読にしました。');

            $firstReadAt = DB::table('notifications')->where('id', $id)->value('read_at');

            Carbon::setTestNow('2026-11-04 10:00:00');

            $this->post(route('notifications.read', $id))
                ->assertRedirect(route('notifications.index'))
                ->assertSessionHas('success', '通知を既読にしました。');

            $this->assertSame(
                $firstReadAt,
                DB::table('notifications')->where('id', $id)->value('read_at')
            );
            $this->assertDatabaseCount('notifications', 1);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_authenticated_user_cannot_mark_another_users_notification_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $id = $this->createNotification($otherUser, '他人の通知', '本文');

        $this->actingAs($user)
            ->post(route('notifications.read', $id))
            ->assertNotFound();

        $this->assertDatabaseHas('notifications', [
            'id' => $id,
            'read_at' => null,
        ]);
    }

    public function test_read_notification_remains_visible_without_read_action(): void
    {
        $user = User::factory()->create();
        $id = $this->createNotification($user, '既読済み通知', '既読本文', now());

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('既読済み通知')
            ->assertDontSee(route('notifications.read', $id), false);
    }

    public function test_navigation_displays_correct_unread_notification_count(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->createNotification($user, '未読1', '本文');
        $this->createNotification($user, '未読2', '本文');
        $this->createNotification($user, '既読', '本文', now());
        $this->createNotification($otherUser, '他人の未読', '本文');

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('data-testid="unread-notification-count"', false)
            ->assertSeeInOrder([
                'data-testid="unread-notification-count"',
                '>2</span>',
            ], false);
    }

    public function test_unread_notification_count_decreases_after_marking_notification_as_read(): void
    {
        $user = User::factory()->create();
        $readNotificationId = $this->createNotification($user, '既読対象', '本文');
        $this->createNotification($user, '未読のまま', '本文');

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'data-testid="unread-notification-count"',
                '>2</span>',
            ], false);

        $this->post(route('notifications.read', $readNotificationId))
            ->assertRedirect(route('notifications.index'));

        $this->get(route('notifications.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'data-testid="unread-notification-count"',
                '>1</span>',
            ], false);

        $this->assertNotNull(
            DB::table('notifications')->where('id', $readNotificationId)->value('read_at')
        );
    }

    public function test_index_displays_empty_state_without_unread_badge(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('通知はありません。')
            ->assertDontSee('data-testid="unread-notification-count"', false);

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(0, $user->notifications()->count());
    }

    private function createNotification(
        User $user,
        string $title,
        string $body,
        mixed $readAt = null,
        mixed $createdAt = null
    ): string {
        $id = (string) Str::uuid();
        $createdAt ??= now();

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'App\\Notifications\\ReadingPlanReminder',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'title' => $title,
                'body' => $body,
            ], JSON_UNESCAPED_UNICODE),
            'read_at' => $readAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $id;
    }
}
