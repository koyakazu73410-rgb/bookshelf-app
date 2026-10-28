<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use App\Policies\BookPolicy;
use App\Policies\ReviewPolicy;
use PHPUnit\Framework\TestCase;

class PolicyTest extends TestCase
{
    public function test_book_policy_allows_owner_and_denies_other_user(): void
    {
        $owner = new User(['name' => '所有者']);
        $owner->id = 10;
        $otherUser = new User(['name' => '他ユーザー']);
        $otherUser->id = 20;
        $book = new Book(['user_id' => 10]);
        $policy = new BookPolicy;

        $this->assertTrue($policy->update($owner, $book));
        $this->assertTrue($policy->delete($owner, $book));
        $this->assertFalse($policy->update($otherUser, $book));
        $this->assertFalse($policy->delete($otherUser, $book));
    }

    public function test_review_policy_allows_owner_and_denies_other_user(): void
    {
        $owner = new User(['name' => '投稿者']);
        $owner->id = 10;
        $otherUser = new User(['name' => '他ユーザー']);
        $otherUser->id = 20;
        $review = new Review(['user_id' => 10]);
        $policy = new ReviewPolicy;

        $this->assertTrue($policy->update($owner, $review));
        $this->assertTrue($policy->delete($owner, $review));
        $this->assertFalse($policy->update($otherUser, $review));
        $this->assertFalse($policy->delete($otherUser, $review));
    }
}
