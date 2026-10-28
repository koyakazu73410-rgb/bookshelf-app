<?php

namespace Tests\Feature\Migration;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdvancedTableMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reading_plans_table_has_the_approved_structure(): void
    {
        $this->assertTrue(Schema::hasColumns('reading_plans', [
            'id',
            'user_id',
            'book_id',
            'target_date',
            'status',
            'completed_at',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_reading_plan_can_be_created_with_default_status_and_nullable_completed_at(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $id = DB::table('reading_plans')->insertGetId([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => '2026-12-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('reading_plans', [
            'id' => $id,
            'status' => 'in_progress',
            'completed_at' => null,
        ]);
    }

    public function test_reading_plan_accepts_each_approved_status(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        foreach (['in_progress', 'completed', 'expired'] as $status) {
            DB::table('reading_plans')->insert([
                'user_id' => $user->id,
                'book_id' => $book->id,
                'target_date' => '2026-12-31',
                'status' => $status,
                'completed_at' => $status === 'completed' ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->assertSame(3, DB::table('reading_plans')->count());
    }

    public function test_reading_plan_rejects_an_unapproved_status(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $this->expectException(QueryException::class);

        DB::table('reading_plans')->insert([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => '2026-12-31',
            'status' => 'cancelled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_status_migration_converts_cancelled_to_expired_without_losing_data(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();
        $migration = require database_path(
            'migrations/2026_10_19_000000_replace_cancelled_with_expired_in_reading_plans_status.php'
        );

        $migration->down();

        $id = DB::table('reading_plans')->insertGetId([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => '2026-12-31',
            'status' => 'cancelled',
            'completed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();

        $this->assertDatabaseHas('reading_plans', [
            'id' => $id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => '2026-12-31',
            'status' => 'expired',
            'completed_at' => null,
        ]);
    }

    public function test_reading_plan_rejects_nonexistent_foreign_keys(): void
    {
        $this->expectException(QueryException::class);

        DB::table('reading_plans')->insert([
            'user_id' => 999999,
            'book_id' => 999999,
            'target_date' => '2026-12-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_deleting_a_user_or_book_cascades_to_reading_plans(): void
    {
        $user = User::factory()->create();
        $firstBook = Book::factory()->for($user)->create();
        $secondBook = Book::factory()->for($user)->create();

        foreach ([$firstBook, $secondBook] as $book) {
            DB::table('reading_plans')->insert([
                'user_id' => $user->id,
                'book_id' => $book->id,
                'target_date' => '2026-12-31',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $firstBook->delete();
        $this->assertDatabaseMissing('reading_plans', ['book_id' => $firstBook->id]);
        $this->assertDatabaseHas('reading_plans', ['book_id' => $secondBook->id]);

        $user->delete();
        $this->assertDatabaseMissing('reading_plans', ['book_id' => $secondBook->id]);
    }

    public function test_notifications_table_uses_laravel_database_notification_structure(): void
    {
        $this->assertTrue(Schema::hasColumns('notifications', [
            'id',
            'type',
            'notifiable_type',
            'notifiable_id',
            'data',
            'read_at',
            'created_at',
            'updated_at',
        ]));

        $user = User::factory()->create();
        $id = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'App\\Notifications\\ReadingPlanReminder',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['title' => '読書計画のお知らせ'], JSON_UNESCAPED_UNICODE),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('notifications', [
            'id' => $id,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'read_at' => null,
        ]);
    }

    public function test_notification_rejects_a_duplicate_uuid(): void
    {
        $user = User::factory()->create();
        $id = (string) Str::uuid();
        $notification = [
            'id' => $id,
            'type' => 'App\\Notifications\\ReadingPlanReminder',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('notifications')->insert($notification);

        $this->expectException(QueryException::class);
        DB::table('notifications')->insert($notification);
    }
}
