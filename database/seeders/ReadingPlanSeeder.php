<?php

namespace Database\Seeders;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReadingPlanSeeder extends Seeder
{
    /**
     * The six scenarios defined by the advanced requirements.
     *
     * Users and books are resolved by stable business keys rather than IDs.
     * Each user/book pair is reserved for one seed scenario.
     *
     * @var list<array{email: string, isbn: string, target_offset: int, status: ReadingPlanStatus, completed_offset: int|null}>
     */
    private const SCENARIOS = [
        [
            'email' => 'yamada@example.com',
            'isbn' => '9784101010014',
            'target_offset' => 3,
            'status' => ReadingPlanStatus::InProgress,
            'completed_offset' => null,
        ],
        [
            'email' => 'yamada@example.com',
            'isbn' => '9784422100524',
            'target_offset' => 0,
            'status' => ReadingPlanStatus::InProgress,
            'completed_offset' => null,
        ],
        [
            'email' => 'yamada@example.com',
            'isbn' => '9784873115658',
            'target_offset' => -3,
            'status' => ReadingPlanStatus::InProgress,
            'completed_offset' => null,
        ],
        [
            'email' => 'yamada@example.com',
            'isbn' => '9784101010021',
            'target_offset' => 7,
            'status' => ReadingPlanStatus::InProgress,
            'completed_offset' => null,
        ],
        [
            'email' => 'yamada@example.com',
            'isbn' => '9784048930598',
            'target_offset' => -10,
            'status' => ReadingPlanStatus::Completed,
            'completed_offset' => -5,
        ],
        [
            'email' => 'suzuki@example.com',
            'isbn' => '9784478025819',
            'target_offset' => 5,
            'status' => ReadingPlanStatus::InProgress,
            'completed_offset' => null,
        ],
    ];

    /**
     * Seed the reading-plan scenarios without overwriting manual plans.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $today = Carbon::today('Asia/Tokyo');
            $emails = collect(self::SCENARIOS)->pluck('email')->unique()->values();
            $isbns = collect(self::SCENARIOS)->pluck('isbn')->unique()->values();

            $users = User::query()
                ->whereIn('email', $emails)
                ->get()
                ->keyBy('email');

            $books = Book::query()
                ->whereIn('isbn', $isbns)
                ->get()
                ->keyBy('isbn');

            $missingEmails = $emails->diff($users->keys());
            $missingIsbns = $isbns->diff($books->keys());

            if ($missingEmails->isNotEmpty() || $missingIsbns->isNotEmpty()) {
                throw new RuntimeException(sprintf(
                    'ReadingPlanSeeder prerequisites are missing. users=[%s], books=[%s]',
                    $missingEmails->implode(', '),
                    $missingIsbns->implode(', ')
                ));
            }

            if ($books->pluck('id')->unique()->count() !== count(self::SCENARIOS)) {
                throw new RuntimeException('ReadingPlanSeeder requires six different books.');
            }

            foreach (self::SCENARIOS as $scenario) {
                $user = $users->get($scenario['email']);
                $book = $books->get($scenario['isbn']);
                $targetDate = $today->copy()->addDays($scenario['target_offset']);
                $completedAt = $scenario['completed_offset'] === null
                    ? null
                    : $today->copy()->addDays($scenario['completed_offset'])->startOfDay();

                $existingPlans = ReadingPlan::query()
                    ->where('user_id', $user->id)
                    ->where('book_id', $book->id)
                    ->lockForUpdate()
                    ->get();

                if ($existingPlans->isNotEmpty()) {
                    if ($existingPlans->count() === 1
                        && $this->matchesScenario(
                            $existingPlans->first(),
                            $targetDate,
                            $scenario['status'],
                            $completedAt
                        )) {
                        continue;
                    }

                    throw new RuntimeException(sprintf(
                        'ReadingPlanSeeder stopped to protect an existing plan for user [%s] and book [%s].',
                        $scenario['email'],
                        $scenario['isbn']
                    ));
                }

                $readingPlan = new ReadingPlan;
                $readingPlan->user()->associate($user);
                $readingPlan->book()->associate($book);
                $readingPlan->target_date = $targetDate;
                $readingPlan->status = $scenario['status'];
                $readingPlan->completed_at = $completedAt;
                $readingPlan->save();
            }
        });
    }

    /**
     * Determine whether an existing record is the same seed scenario.
     */
    private function matchesScenario(
        ReadingPlan $readingPlan,
        Carbon $targetDate,
        ReadingPlanStatus $status,
        ?Carbon $completedAt
    ): bool {
        $completedAtMatches = $completedAt === null
            ? $readingPlan->completed_at === null
            : $readingPlan->completed_at?->toDateString() === $completedAt->toDateString();

        return $readingPlan->target_date->toDateString() === $targetDate->toDateString()
            && $readingPlan->status === $status
            && $completedAtMatches;
    }
}
