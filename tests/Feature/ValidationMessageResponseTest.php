<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ValidationMessageResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_store_returns_japanese_required_messages(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('books.store'), [])
            ->assertSessionHasErrors([
                'title' => 'タイトルを入力してください。',
                'author' => '著者名を入力してください。',
                'genres' => 'ジャンルを1つ以上選択してください。',
            ])
            ->assertSessionDoesntHaveErrors(['isbn', 'published_date']);
    }

    public function test_book_store_returns_japanese_type_and_format_messages(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('books.store'), [
                'title' => ['invalid'],
                'author' => ['invalid'],
                'isbn' => '123',
                'published_date' => 'invalid-date',
                'description' => ['invalid'],
                'image_url' => 'invalid-url',
                'genres' => 'invalid',
            ])->assertSessionHasErrors([
                'title' => 'タイトルは文字列で入力してください。',
                'author' => '著者名は文字列で入力してください。',
                'isbn' => 'ISBNは13桁の数字で入力してください。',
                'published_date' => '正しい日付を入力してください。',
                'description' => '説明は文字列で入力してください。',
                'image_url' => '画像URLは正しいURL形式で入力してください。',
                'genres' => 'ジャンルの形式が正しくありません。',
            ]);
    }

    public function test_book_store_returns_japanese_length_genre_and_unique_messages(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        Book::factory()->create(['isbn' => '9781234567890']);

        $this->actingAs($user)->post(route('books.store'), [
            'title' => str_repeat('あ', 256),
            'author' => str_repeat('あ', 256),
            'isbn' => '9781234567890',
            'published_date' => '2025-01-01',
            'image_url' => 'https://example.com/'.str_repeat('a', 2050),
            'genres' => [$genre->id, $genre->id, 999999],
        ])->assertSessionHasErrors([
            'title' => 'タイトルは255文字以内で入力してください。',
            'author' => '著者名は255文字以内で入力してください。',
            'isbn' => 'このISBNはすでに登録されています。',
            'image_url' => '画像URLは2048文字以内で入力してください。',
            'genres.1' => '同じジャンルを重複して選択することはできません。',
            'genres.2' => '選択されたジャンルが存在しません。',
        ]);
    }

    public function test_review_returns_japanese_validation_messages(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($user)->post(route('reviews.store', $book), [])
            ->assertSessionHasErrors([
                'rating' => '評価は必須です。',
                'comment' => 'コメントを入力してください。',
            ]);

        $this->actingAs($user)->post(route('reviews.store', $book), [
            'rating' => 'five',
            'comment' => ['invalid'],
        ])->assertSessionHasErrors([
            'rating' => '評価は整数で入力してください。',
            'comment' => 'コメントは文字列で入力してください。',
        ]);

        $this->actingAs($user)->post(route('reviews.store', $book), [
            'rating' => 0,
            'comment' => str_repeat('あ', 1001),
        ])->assertSessionHasErrors([
            'rating' => '評価は1以上で入力してください。',
            'comment' => 'コメントは1000文字以内で入力してください。',
        ]);

        $this->actingAs($user)->post(route('reviews.store', $book), [
            'rating' => 6,
            'comment' => '評価上限確認',
        ])->assertSessionHasErrors([
            'rating' => '評価は5以下で入力してください。',
        ]);
    }

    public function test_genre_returns_japanese_validation_messages(): void
    {
        $user = User::factory()->create();
        Genre::factory()->create(['name' => '既存ジャンル']);

        $this->actingAs($user)->post(route('genres.store'), [])
            ->assertSessionHasErrors(['name' => 'ジャンル名を入力してください。']);
        $this->actingAs($user)->post(route('genres.store'), ['name' => ['invalid']])
            ->assertSessionHasErrors(['name' => 'ジャンル名は文字列で入力してください。']);
        $this->actingAs($user)->post(route('genres.store'), ['name' => str_repeat('あ', 256)])
            ->assertSessionHasErrors(['name' => 'ジャンル名は255文字以内で入力してください。']);
        $this->actingAs($user)->post(route('genres.store'), ['name' => '既存ジャンル'])
            ->assertSessionHasErrors(['name' => 'このジャンル名はすでに登録されています。']);
    }

    public function test_api_index_returns_japanese_validation_messages(): void
    {
        $this->getJson('/api/v1/books?keyword[]=x&genre_id=999999&page=0&per_page=101')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonPath('errors.keyword.0', 'キーワードは文字列で入力してください。')
            ->assertJsonPath('errors.genre_id.0', '指定されたジャンルが存在しません。')
            ->assertJsonPath('errors.page.0', 'ページ番号は1以上で入力してください。')
            ->assertJsonPath('errors.per_page.0', '1ページあたりの件数は100以下で入力してください。');
    }

    public function test_api_store_returns_japanese_book_messages(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/books', [])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonPath('errors.title.0', 'タイトルは必須です。')
            ->assertJsonPath('errors.author.0', '著者名を入力してください。')
            ->assertJsonPath('errors.genres.0', 'ジャンルを1つ以上選択してください。')
            ->assertJsonMissingValidationErrors(['isbn', 'published_date']);
    }

    public function test_valid_book_input_has_no_validation_message(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $this->actingAs($user)->post(route('books.store'), [
            'title' => '正常入力書籍',
            'author' => '正常入力著者',
            'isbn' => '9781234567890',
            'published_date' => '2025-01-01',
            'description' => '正常な説明',
            'image_url' => 'https://example.com/book.jpg',
            'genres' => [$genre->id],
        ])->assertSessionHasNoErrors();
    }
}
