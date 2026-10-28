<?php

namespace Tests\Feature;

use App\Services\GoogleBooksService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use LogicException;
use Tests\TestCase;

class GoogleBooksServiceTest extends TestCase
{
    public function test_it_returns_book_information_for_isbn(): void
    {
        config(['services.google_books.api_key' => 'test-api-key']);
        Http::fake([
            'www.googleapis.com/books/v1/volumes*' => Http::response([
                'totalItems' => 1,
                'items' => [[
                    'volumeInfo' => [
                        'title' => 'テスト書籍',
                        'authors' => ['テスト著者'],
                        'industryIdentifiers' => [[
                            'type' => 'ISBN_13',
                            'identifier' => '9781234567890',
                        ]],
                        'publishedDate' => '2025-01-01',
                        'description' => 'テスト説明',
                        'imageLinks' => ['thumbnail' => 'https://example.com/book.jpg'],
                    ],
                ]],
            ]),
        ]);

        $book = app(GoogleBooksService::class)->findByIsbn('9781234567890');

        $this->assertSame('テスト書籍', $book['title']);
        $this->assertSame(['テスト著者'], $book['authors']);
        Http::assertSent(fn (Request $request) => str_starts_with(
            $request->url(),
            'https://www.googleapis.com/books/v1/volumes?'
        )
            && $request['q'] === 'isbn:9781234567890'
            && $request['key'] === 'test-api-key'
        );
    }

    public function test_it_throws_exception_when_google_books_api_returns_error(): void
    {
        config(['services.google_books.api_key' => 'test-api-key']);
        Http::fake([
            'www.googleapis.com/books/v1/volumes*' => Http::response([
                'error' => ['message' => 'Service unavailable'],
            ], 503),
        ]);

        $this->expectException(RequestException::class);

        app(GoogleBooksService::class)->findByIsbn('9781234567890');
    }

    public function test_it_falls_back_to_general_search_and_returns_matching_book(): void
    {
        config(['services.google_books.api_key' => 'test-api-key']);
        Http::fakeSequence()
            ->push(['totalItems' => 0])
            ->push(['totalItems' => 0])
            ->push([
                'totalItems' => 1,
                'items' => [[
                    'volumeInfo' => [
                        'title' => '一般検索で見つかった書籍',
                        'authors' => ['テスト著者'],
                        'industryIdentifiers' => [[
                            'type' => 'ISBN_13',
                            'identifier' => '9780132481724',
                        ]],
                    ],
                ]],
            ]);

        $book = app(GoogleBooksService::class)->findByIsbn('9780132481724');

        $this->assertSame('一般検索で見つかった書籍', $book['title']);
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request) => $request['q'] === '9780132481724');
    }

    public function test_it_falls_back_to_isbn_ten_search_for_an_isbn_thirteen(): void
    {
        config(['services.google_books.api_key' => 'test-api-key']);
        Http::fakeSequence()
            ->push(['totalItems' => 0])
            ->push([
                'items' => [[
                    'volumeInfo' => [
                        'title' => 'ISBN-10検索で見つかった書籍',
                        'industryIdentifiers' => [[
                            'type' => 'ISBN_10',
                            'identifier' => '0132481723',
                        ]],
                    ],
                ]],
            ]);

        $book = app(GoogleBooksService::class)->findByIsbn('9780132481724');

        $this->assertSame('ISBN-10検索で見つかった書籍', $book['title']);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $request['q'] === 'isbn:0132481723');
    }

    public function test_it_accepts_matching_isbn_ten_identifier_for_requested_isbn_thirteen(): void
    {
        config(['services.google_books.api_key' => 'test-api-key']);
        Http::fakeSequence()
            ->push([
                'items' => [[
                    'volumeInfo' => [
                        'title' => 'ISBN変換対応書籍',
                        'industryIdentifiers' => [[
                            'type' => 'ISBN_10',
                            'identifier' => '0132481723',
                        ]],
                    ],
                ]],
            ]);

        $book = app(GoogleBooksService::class)->findByIsbn('9780132481724');

        $this->assertSame('ISBN変換対応書籍', $book['title']);
        Http::assertSentCount(1);
    }

    public function test_it_finds_matching_book_after_unrelated_general_search_candidate(): void
    {
        config(['services.google_books.api_key' => 'test-api-key']);
        Http::fakeSequence()
            ->push(['totalItems' => 0])
            ->push(['totalItems' => 0])
            ->push([
                'items' => [
                    [
                        'volumeInfo' => [
                            'title' => '別の書籍',
                            'industryIdentifiers' => [[
                                'type' => 'ISBN_13',
                                'identifier' => '9781234567897',
                            ]],
                        ],
                    ],
                    [
                        'volumeInfo' => [
                            'title' => '後続の一致書籍',
                            'industryIdentifiers' => [[
                                'type' => 'ISBN_13',
                                'identifier' => '9780132481724',
                            ]],
                        ],
                    ],
                ],
            ]);

        $book = app(GoogleBooksService::class)->findByIsbn('9780132481724');

        $this->assertSame('後続の一致書籍', $book['title']);
        Http::assertSentCount(3);
    }

    public function test_it_rejects_unrelated_book_returned_by_general_search(): void
    {
        config(['services.google_books.api_key' => 'test-api-key']);
        Http::fakeSequence()
            ->push([
                'items' => [[
                    'volumeInfo' => [
                        'title' => 'ISBN指定検索で返った別の書籍',
                        'industryIdentifiers' => [[
                            'type' => 'ISBN_13',
                            'identifier' => '9781234567897',
                        ]],
                    ],
                ]],
            ])
            ->push(['totalItems' => 0])
            ->push([
                'items' => [[
                    'volumeInfo' => [
                        'title' => '別の書籍',
                        'industryIdentifiers' => [[
                            'type' => 'ISBN_13',
                            'identifier' => '9781234567897',
                        ]],
                    ],
                ]],
            ])
            ->push(['totalItems' => 0]);

        $book = app(GoogleBooksService::class)->findByIsbn('9780132481724');

        $this->assertNull($book);
        Http::assertSentCount(4);
    }

    public function test_it_returns_null_when_all_bounded_searches_have_no_results(): void
    {
        config(['services.google_books.api_key' => 'test-api-key']);
        $queries = [];
        Http::fake(function (Request $request) use (&$queries) {
            $queries[] = $request['q'];

            return Http::response(['totalItems' => 0]);
        });

        $book = app(GoogleBooksService::class)->findByIsbn('9780132481724');

        $this->assertNull($book);
        $this->assertSame([
            'isbn:9780132481724',
            'isbn:0132481723',
            '9780132481724',
            '0132481723',
        ], $queries);
    }

    public function test_it_sends_configured_api_key_without_hardcoding_it(): void
    {
        config(['services.google_books.api_key' => 'test-api-key']);
        Http::fake([
            'www.googleapis.com/books/v1/volumes*' => Http::response(['totalItems' => 0]),
        ]);

        app(GoogleBooksService::class)->findByIsbn('9781234567890');

        Http::assertSent(fn (Request $request) => $request['key'] === 'test-api-key');
    }

    public function test_it_rejects_request_when_api_key_is_not_configured(): void
    {
        config(['services.google_books.api_key' => null]);
        Http::fake();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Google Books APIキーが設定されていません。');

        try {
            app(GoogleBooksService::class)->findByIsbn('9781234567890');
        } finally {
            Http::assertNothingSent();
        }
    }
}
