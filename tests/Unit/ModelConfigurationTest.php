<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class ModelConfigurationTest extends TestCase
{
    public function test_user_model_has_expected_fillable_hidden_and_cast_attributes(): void
    {
        $user = new User;

        $this->assertSame(['name', 'email', 'password'], $user->getFillable());
        $this->assertSame(['password', 'remember_token'], $user->getHidden());
        $this->assertSame('datetime', $user->getCasts()['email_verified_at']);
        $this->assertSame('hashed', $user->getCasts()['password']);
    }

    public function test_book_model_has_expected_fillable_attributes(): void
    {
        $this->assertSame([
            'user_id',
            'title',
            'author',
            'isbn',
            'published_date',
            'description',
            'image_url',
        ], (new Book)->getFillable());
    }

    public function test_review_model_has_expected_fillable_attributes(): void
    {
        $this->assertSame([
            'user_id',
            'book_id',
            'rating',
            'comment',
        ], (new Review)->getFillable());
    }

    public function test_genre_model_has_expected_fillable_attributes(): void
    {
        $this->assertSame(['name'], (new Genre)->getFillable());
    }
}
