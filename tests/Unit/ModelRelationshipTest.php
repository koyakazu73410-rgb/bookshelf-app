<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_user_relation_is_belongs_to_and_returns_owner(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $this->assertInstanceOf(BelongsTo::class, $book->user());
        $this->assertTrue($book->user->is($user));
    }

    public function test_book_reviews_relation_is_has_many_and_returns_reviews(): void
    {
        $book = Book::factory()->create();
        $review = Review::factory()->for($book)->create();

        $this->assertInstanceOf(HasMany::class, $book->reviews());
        $this->assertTrue($book->reviews->contains($review));
    }

    public function test_book_genres_relation_is_belongs_to_many_and_returns_genres(): void
    {
        $book = Book::factory()->create();
        $genre = Genre::factory()->create();
        $book->genres()->attach($genre);

        $this->assertInstanceOf(BelongsToMany::class, $book->genres());
        $this->assertTrue($book->genres->contains($genre));
    }

    public function test_book_favorite_users_relation_is_belongs_to_many_and_returns_users(): void
    {
        $book = Book::factory()->create();
        $user = User::factory()->create();
        $book->favoriteUsers()->attach($user);

        $this->assertInstanceOf(BelongsToMany::class, $book->favoriteUsers());
        $this->assertTrue($book->favoriteUsers->contains($user));
    }

    public function test_genre_books_relation_is_belongs_to_many_and_returns_books(): void
    {
        $genre = Genre::factory()->create();
        $book = Book::factory()->create();
        $genre->books()->attach($book);

        $this->assertInstanceOf(BelongsToMany::class, $genre->books());
        $this->assertTrue($genre->books->contains($book));
    }

    public function test_review_book_and_user_relations_are_belongs_to_and_return_models(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $review = Review::factory()->for($user)->for($book)->create();

        $this->assertInstanceOf(BelongsTo::class, $review->book());
        $this->assertInstanceOf(BelongsTo::class, $review->user());
        $this->assertTrue($review->book->is($book));
        $this->assertTrue($review->user->is($user));
    }

    public function test_review_liked_users_relation_is_belongs_to_many_and_returns_users(): void
    {
        $review = Review::factory()->create();
        $user = User::factory()->create();
        $review->likedUsers()->attach($user);

        $this->assertInstanceOf(BelongsToMany::class, $review->likedUsers());
        $this->assertTrue($review->likedUsers->contains($user));
    }

    public function test_user_has_many_relations_return_books_and_reviews(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();
        $review = Review::factory()->for($user)->create();

        $this->assertInstanceOf(HasMany::class, $user->books());
        $this->assertInstanceOf(HasMany::class, $user->reviews());
        $this->assertTrue($user->books->contains($book));
        $this->assertTrue($user->reviews->contains($review));
    }

    public function test_user_many_to_many_relations_return_favorites_and_liked_reviews(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $review = Review::factory()->create();
        $user->favoriteBooks()->attach($book);
        $user->likedReviews()->attach($review);

        $this->assertInstanceOf(BelongsToMany::class, $user->favoriteBooks());
        $this->assertInstanceOf(BelongsToMany::class, $user->likedReviews());
        $this->assertTrue($user->favoriteBooks->contains($book));
        $this->assertTrue($user->likedReviews->contains($review));
    }
}
