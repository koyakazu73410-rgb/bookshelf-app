<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace the provisional cancelled status with expired.
     */
    public function up(): void
    {
        $this->changeStatusValues([
            'in_progress',
            'completed',
            'cancelled',
            'expired',
        ]);

        DB::table('reading_plans')
            ->where('status', 'cancelled')
            ->update(['status' => 'expired']);

        $this->changeStatusValues([
            'in_progress',
            'completed',
            'expired',
        ]);
    }

    /**
     * Restore the provisional cancelled status.
     */
    public function down(): void
    {
        $this->changeStatusValues([
            'in_progress',
            'completed',
            'cancelled',
            'expired',
        ]);

        DB::table('reading_plans')
            ->where('status', 'expired')
            ->update(['status' => 'cancelled']);

        $this->changeStatusValues([
            'in_progress',
            'completed',
            'cancelled',
        ]);
    }

    /**
     * Change the allowed status values while preserving NOT NULL and the default.
     *
     * @param  array<int, string>  $statuses
     */
    private function changeStatusValues(array $statuses): void
    {
        if (DB::getDriverName() === 'mysql') {
            $values = collect($statuses)
                ->map(static fn (string $status): string => "'{$status}'")
                ->implode(',');

            DB::statement(
                "ALTER TABLE `reading_plans` MODIFY `status` ENUM({$values}) NOT NULL DEFAULT 'in_progress'"
            );

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqliteTable($statuses);

            return;
        }

        throw new RuntimeException('Unsupported database driver: '.DB::getDriverName());
    }

    /**
     * Rebuild the SQLite table because SQLite cannot alter an enum check constraint.
     *
     * @param  array<int, string>  $statuses
     */
    private function rebuildSqliteTable(array $statuses): void
    {
        $temporaryTable = 'reading_plans_status_backup';
        $columns = [
            'id',
            'user_id',
            'book_id',
            'target_date',
            'status',
            'completed_at',
            'created_at',
            'updated_at',
        ];

        Schema::disableForeignKeyConstraints();

        try {
            Schema::rename('reading_plans', $temporaryTable);

            Schema::create('reading_plans', function (Blueprint $table) use ($statuses): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('book_id')->constrained()->cascadeOnDelete();
                $table->date('target_date');
                $table->enum('status', $statuses)->default('in_progress');
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });

            DB::table('reading_plans')->insertUsing(
                $columns,
                DB::table($temporaryTable)->select($columns)
            );

            Schema::drop($temporaryTable);
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};
