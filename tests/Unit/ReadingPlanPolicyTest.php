<?php

namespace Tests\Unit;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Policies\ReadingPlanPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingPlanPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_is_allowed_to_update_delete_and_complete_plan(): void
    {
        $owner = User::factory()->create();
        $readingPlan = ReadingPlan::factory()->for($owner)->create();
        $policy = new ReadingPlanPolicy;

        $this->assertTrue($policy->update($owner, $readingPlan));
        $this->assertTrue($policy->delete($owner, $readingPlan));
        $this->assertTrue($policy->complete($owner, $readingPlan));
        $this->assertTrue($owner->can('update', $readingPlan));
        $this->assertTrue($owner->can('delete', $readingPlan));
        $this->assertTrue($owner->can('complete', $readingPlan));
    }

    public function test_non_owner_is_denied_update_delete_and_complete_plan(): void
    {
        $readingPlan = ReadingPlan::factory()->create();
        $otherUser = User::factory()->create();
        $policy = new ReadingPlanPolicy;

        $this->assertFalse($policy->update($otherUser, $readingPlan));
        $this->assertFalse($policy->delete($otherUser, $readingPlan));
        $this->assertFalse($policy->complete($otherUser, $readingPlan));
        $this->assertFalse($otherUser->can('update', $readingPlan));
        $this->assertFalse($otherUser->can('delete', $readingPlan));
        $this->assertFalse($otherUser->can('complete', $readingPlan));
    }

    public function test_owner_cannot_update_completed_plan(): void
    {
        $owner = User::factory()->create();
        $readingPlan = ReadingPlan::factory()->for($owner)->create([
            'status' => ReadingPlanStatus::Completed,
        ]);
        $policy = new ReadingPlanPolicy;

        $this->assertFalse($policy->update($owner, $readingPlan));
        $this->assertFalse($owner->can('update', $readingPlan));
    }
}
