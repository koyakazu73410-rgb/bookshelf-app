<?php

namespace Tests\Unit;

use Tests\TestCase;

class GoogleBooksConfigurationTest extends TestCase
{
    public function test_google_books_configuration_is_loaded(): void
    {
        $this->assertSame(
            'https://www.googleapis.com/books/v1',
            config('services.google_books.base_url')
        );
        $this->assertArrayHasKey('api_key', config('services.google_books'));
    }
}
