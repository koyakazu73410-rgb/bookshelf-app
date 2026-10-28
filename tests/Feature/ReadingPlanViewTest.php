<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReadingPlanViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_displays_only_authenticated_users_reading_plans(): void
    {
        $user = User::factory()->create();
        $ownBook = Book::factory()->create(['title' => '自分の読書計画対象書籍']);
        $otherBook = Book::factory()->create(['title' => '他人の読書計画対象書籍']);
        ReadingPlan::factory()->for($user)->for($ownBook)->create();
        ReadingPlan::factory()->for($otherBook)->create();

        $this->actingAs($user)
            ->get(route('reading-plans.index'))
            ->assertOk()
            ->assertSee('自分の読書計画対象書籍')
            ->assertDontSee('他人の読書計画対象書籍')
            ->assertSee('進行中');
    }

    public function test_create_screen_displays_books_and_does_not_include_status_input(): void
    {
        $book = Book::factory()->create(['title' => '登録画面用書籍']);

        $this->actingAs(User::factory()->create())
            ->get(route('reading-plans.create'))
            ->assertOk()
            ->assertSee('新規読書計画作成')
            ->assertSee('登録画面用書籍')
            ->assertSee('name="book_id"', false)
            ->assertSee('name="target_date"', false)
            ->assertDontSee('name="status"', false);
    }

    public function test_edit_screen_displays_book_and_current_target_date_without_mutable_book_or_status_fields(): void
    {
        $owner = User::factory()->create();
        $book = Book::factory()->create(['title' => '編集画面用書籍']);
        $readingPlan = ReadingPlan::factory()->for($owner)->for($book)->create([
            'target_date' => '2026-12-15',
        ]);

        $this->actingAs($owner)
            ->get(route('reading-plans.edit', $readingPlan))
            ->assertOk()
            ->assertSee('編集画面用書籍')
            ->assertSee('value="2026-12-15"', false)
            ->assertDontSee('name="book_id"', false)
            ->assertDontSee('name="status"', false);
    }

    public function test_non_owner_cannot_display_edit_screen(): void
    {
        $readingPlan = ReadingPlan::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('reading-plans.edit', $readingPlan))
            ->assertForbidden();
    }

    public function test_index_can_filter_plans_by_status(): void
    {
        $user = User::factory()->create();
        $inProgressBook = Book::factory()->create(['title' => '進行中フィルター対象']);
        $completedBook = Book::factory()->create(['title' => '完了フィルター対象']);
        $expiredBook = Book::factory()->create(['title' => '期限切れフィルター対象']);
        ReadingPlan::factory()->for($user)->for($inProgressBook)->create([
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);
        ReadingPlan::factory()->for($user)->for($completedBook)->create([
            'status' => ReadingPlan::STATUS_COMPLETED,
        ]);
        ReadingPlan::factory()->for($user)->for($expiredBook)->create([
            'status' => ReadingPlan::STATUS_EXPIRED,
        ]);

        $this->actingAs($user)
            ->get(route('reading-plans.index', ['status' => ReadingPlan::STATUS_COMPLETED]))
            ->assertOk()
            ->assertSee('完了フィルター対象')
            ->assertDontSee('進行中フィルター対象')
            ->assertDontSee('期限切れフィルター対象')
            ->assertSee('完了');
    }

    public function test_create_screen_displays_japanese_validation_errors_and_old_input(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        $user = User::factory()->create();
        $book = Book::factory()->create(['title' => '入力保持確認用書籍']);

        $this->actingAs($user)
            ->from(route('reading-plans.create'))
            ->post(route('reading-plans.store'), [
                'book_id' => $book->id,
                'target_date' => '2026-10-19',
            ])
            ->assertRedirect(route('reading-plans.create'));

        $this->get(route('reading-plans.create'))
            ->assertOk()
            ->assertSee('目標日は本日以降の日付を入力してください。')
            ->assertSee('value="2026-10-19"', false)
            ->assertSee("value=\"{$book->id}\" selected", false);
    }

    public function test_index_displays_empty_message_when_user_has_no_plans(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('reading-plans.index'))
            ->assertOk()
            ->assertSee('該当する読書計画はありません。');
    }
}
