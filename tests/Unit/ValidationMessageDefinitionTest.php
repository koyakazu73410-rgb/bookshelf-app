<?php

namespace Tests\Unit;

use App\Http\Requests\Api\V1\IndexBookRequest;
use App\Http\Requests\Api\V1\StoreBookRequest as ApiStoreBookRequest;
use App\Http\Requests\ReviewRequest;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\StoreGenreRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Http\Requests\UpdateGenreRequest;
use PHPUnit\Framework\TestCase;

class ValidationMessageDefinitionTest extends TestCase
{
    public function test_web_book_requests_define_requirement_sheet_messages(): void
    {
        $expected = [
            'title.required' => 'タイトルを入力してください。',
            'title.string' => 'タイトルは文字列で入力してください。',
            'title.max' => 'タイトルは255文字以内で入力してください。',
            'author.required' => '著者名を入力してください。',
            'author.string' => '著者名は文字列で入力してください。',
            'author.max' => '著者名は255文字以内で入力してください。',
            'isbn.required' => 'ISBNを入力してください。',
            'isbn.digits' => 'ISBNは13桁の数字で入力してください。',
            'isbn.unique' => 'このISBNはすでに登録されています。',
            'published_date.required' => '出版日を入力してください。',
            'published_date.date' => '正しい日付を入力してください。',
            'description.string' => '説明は文字列で入力してください。',
            'image_url.url' => '画像URLは正しいURL形式で入力してください。',
            'image_url.max' => '画像URLは2048文字以内で入力してください。',
            'genres.required' => 'ジャンルを1つ以上選択してください。',
            'genres.array' => 'ジャンルの形式が正しくありません。',
            'genres.min' => 'ジャンルを1つ以上選択してください。',
            'genres.*.integer' => 'ジャンルの値が正しくありません。',
            'genres.*.distinct' => '同じジャンルを重複して選択することはできません。',
            'genres.*.exists' => '選択されたジャンルが存在しません。',
        ];

        $this->assertSame($expected, (new StoreBookRequest)->messages());
        $this->assertSame($expected, (new UpdateBookRequest)->messages());
    }

    public function test_review_request_defines_requirement_sheet_messages(): void
    {
        $this->assertSame([
            'rating.required' => '評価は必須です。',
            'rating.integer' => '評価は整数で入力してください。',
            'rating.min' => '評価は1以上で入力してください。',
            'rating.max' => '評価は5以下で入力してください。',
            'comment.required' => 'コメントを入力してください。',
            'comment.string' => 'コメントは文字列で入力してください。',
            'comment.max' => 'コメントは1000文字以内で入力してください。',
        ], (new ReviewRequest)->messages());
    }

    public function test_genre_requests_define_requirement_sheet_messages(): void
    {
        $expected = [
            'name.required' => 'ジャンル名を入力してください。',
            'name.string' => 'ジャンル名は文字列で入力してください。',
            'name.max' => 'ジャンル名は255文字以内で入力してください。',
            'name.unique' => 'このジャンル名はすでに登録されています。',
        ];

        $this->assertSame($expected, (new StoreGenreRequest)->messages());
        $this->assertSame($expected, (new UpdateGenreRequest)->messages());
    }

    public function test_api_index_request_defines_requirement_sheet_messages(): void
    {
        $this->assertSame([
            'keyword.string' => 'キーワードは文字列で入力してください。',
            'keyword.max' => 'キーワードは255文字以内で入力してください。',
            'genre_id.integer' => 'ジャンルIDは整数で入力してください。',
            'genre_id.exists' => '指定されたジャンルが存在しません。',
            'page.integer' => 'ページ番号は整数で入力してください。',
            'page.min' => 'ページ番号は1以上で入力してください。',
            'per_page.integer' => '1ページあたりの件数は整数で入力してください。',
            'per_page.min' => '1ページあたりの件数は1以上で入力してください。',
            'per_page.max' => '1ページあたりの件数は100以下で入力してください。',
        ], (new IndexBookRequest)->messages());
    }

    public function test_api_book_request_uses_book_validation_messages(): void
    {
        $messages = (new ApiStoreBookRequest)->messages();

        $this->assertArrayNotHasKey('user_id.required', $messages);
        $this->assertSame('タイトルを入力してください。', $messages['title.required']);
        $this->assertSame('同じジャンルを重複して選択することはできません。', $messages['genres.*.distinct']);
    }
}
