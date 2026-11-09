<?php

namespace App\Console\Commands;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use Illuminate\Console\Command;

class ExpireReadingPlans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reading-plans:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '期限を過ぎた進行中の読書計画を期限切れに更新します。';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = now('Asia/Tokyo')->startOfDay();

        $updatedCount = ReadingPlan::query()
            ->where('status', ReadingPlanStatus::InProgress->value)
            ->whereDate('target_date', '<', $today->toDateString())
            ->update([
                'status' => ReadingPlanStatus::Expired->value,
            ]);

        $this->info("{$updatedCount}件の読書計画を期限切れに更新しました。");

        return self::SUCCESS;
    }
}
