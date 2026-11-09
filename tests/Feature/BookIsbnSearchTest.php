<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookIsbnSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_create_page_displays_isbn_search_and_manual_entry_form(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('books.create'));

        $response->assertOk()
            ->assertSee('ISBN から書籍情報を自動入力')
            ->assertSee('id="isbn-search"', false)
            ->assertSee('id="title"', false)
            ->assertSee('id="author"', false);

        $html = $response->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/<label[^>]+for="isbn"[^>]*>(?:(?!<\/label>).)*<span[^>]+class="text-red-500"[^>]*>/s',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<label[^>]+for="published_date"[^>]*>(?:(?!<\/label>).)*<span[^>]+class="text-red-500"[^>]*>/s',
            $html
        );
    }

    public function test_create_page_includes_client_side_safeguards_for_repeated_isbn_searches(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('books.create'));

        $response->assertOk()
            ->assertSee('id="book-submit-btn"', false)
            ->assertSee('let lastAutoFilledValues = {}', false)
            ->assertSee('field.value === lastAutoFilledValues[fieldId]', false)
            ->assertSee("document.getElementById('isbn').value = isbn", false)
            ->assertSee('hideSearchMessage(errorEl)', false)
            ->assertSee('hideSearchMessage(successEl)', false)
            ->assertSee('element.hidden = true', false)
            ->assertSee('element.hidden = false', false)
            ->assertSee('submitButton.disabled = true', false)
            ->assertSee('submitButton.disabled = false', false);
    }

    public function test_guest_cannot_search_book_by_isbn(): void
    {
        $this->get(route('books.isbn.search', '9781234567890'))
            ->assertRedirect('/login');
    }

    public function test_isbn_search_returns_data_for_form_autofill(): void
    {
        config(['services.google_books.api_key' => 'test-api-key']);
        Http::fake([
            'www.googleapis.com/books/v1/volumes*' => Http::response([
                'totalItems' => 1,
                'items' => [[
                    'volumeInfo' => [
                        'title' => 'テスト書籍',
                        'authors' => ['著者A', '著者B'],
                        'industryIdentifiers' => [[
                            'type' => 'ISBN_13',
                            'identifier' => '9781234567890',
                        ]],
                        'publishedDate' => '2025-01-02',
                        'description' => 'テスト説明',
                        'imageLinks' => [
                            'thumbnail' => 'https://example.com/book.jpg',
                        ],
                    ],
                ]],
            ]),
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('books.isbn.search', '9781234567890'))
            ->assertOk()
            ->assertExactJson([
                'title' => 'テスト書籍',
                'author' => '著者A、著者B',
                'isbn' => '9781234567890',
                'published_date' => '2025-01-02',
                'description' => 'テスト説明',
                'image_url' => 'https://example.com/book.jpg',
            ]);

        Http::assertSent(fn (Request $request) => $request['q'] === 'isbn:9781234567890'
            && $request['key'] === 'test-api-key'
        );
    }

    public function test_invalid_isbn_returns_japanese_validation_message_without_api_call(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->getJson(route('books.isbn.search', 'invalid-isbn'))
            ->assertBadRequest()
            ->assertJsonPath('message', 'ISBNの入力内容に誤りがあります。')
            ->assertJsonValidationErrors([
                'isbn' => 'ISBNは13桁の数字で入力してください。',
            ]);

        Http::assertNothingSent();
    }

    public function test_no_search_result_returns_japanese_message_and_keeps_manual_entry_available(): void
    {
        config(['services.google_books.api_key' => 'test-api-key']);
        Http::fake([
            'www.googleapis.com/books/v1/volumes*' => Http::response([
                'totalItems' => 0,
            ]),
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('books.isbn.search', '9781234567890'))
            ->assertNotFound()
            ->assertExactJson([
                'error' => '書籍情報が見つかりませんでした。手動で入力してください。',
            ]);
    }

    public function test_api_error_returns_japanese_message(): void
    {
        config(['services.google_books.api_key' => 'test-api-key']);
        Http::fake([
            'www.googleapis.com/books/v1/volumes*' => Http::response([
                'error' => ['message' => 'Service unavailable'],
            ], 503),
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('books.isbn.search', '9781234567890'))
            ->assertStatus(500)
            ->assertExactJson([
                'error' => 'Google Books APIから書籍情報を取得できませんでした。手動で入力してください。',
            ]);
    }

    public function test_missing_api_key_returns_japanese_configuration_message(): void
    {
        config(['services.google_books.api_key' => null]);
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->getJson(route('books.isbn.search', '9781234567890'))
            ->assertStatus(429)
            ->assertExactJson([
                'error' => 'Google Books APIを利用できません。しばらくしてから再度お試しください。',
            ]);

        Http::assertNothingSent();
    }

    public function test_google_books_rate_limit_returns_429_with_japanese_message(): void
    {
        config(['services.google_books.api_key' => 'test-api-key']);
        Http::fake([
            'www.googleapis.com/books/v1/volumes*' => Http::response([
                'error' => ['message' => 'Quota exceeded'],
            ], 429),
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('books.isbn.search', '9781234567890'))
            ->assertStatus(429)
            ->assertExactJson([
                'error' => 'Google Books APIの利用上限に達しました。しばらくしてから再度お試しください。',
            ]);
    }
}
