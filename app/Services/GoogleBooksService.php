<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use LogicException;

class GoogleBooksService
{
    /**
     * Find the first Google Books volume matching the ISBN.
     *
     * @param  string  $isbn  The normalized ISBN supplied by the application.
     * @return array<string, mixed>|null
     */
    public function findByIsbn(string $isbn): ?array
    {
        $apiKey = config('services.google_books.api_key');

        if (blank($apiKey)) {
            throw new LogicException('Google Books APIキーが設定されていません。');
        }

        $identifiers = $this->equivalentIsbns($isbn);
        $queries = [
            ...array_map(static fn (string $identifier): string => "isbn:{$identifier}", $identifiers),
            ...$identifiers,
        ];

        foreach (array_values(array_unique($queries)) as $query) {
            $response = $this->client()
                ->get('/volumes', [
                    'q' => $query,
                    'key' => $apiKey,
                ])
                ->throw();

            foreach ($response->json('items', []) as $item) {
                $volumeInfo = $item['volumeInfo'] ?? null;

                if (is_array($volumeInfo) && $this->hasMatchingIdentifier($volumeInfo, $isbn)) {
                    return $volumeInfo;
                }
            }
        }

        return null;
    }

    /**
     * Determine whether a volume contains the requested ISBN or its ISBN-10/13 equivalent.
     *
     * @param  array<string, mixed>  $volumeInfo
     */
    private function hasMatchingIdentifier(array $volumeInfo, string $isbn): bool
    {
        $expectedIdentifiers = $this->equivalentIsbns($isbn);

        foreach ($volumeInfo['industryIdentifiers'] ?? [] as $identifier) {
            $value = $this->normalizeIsbn((string) ($identifier['identifier'] ?? ''));

            if (in_array($value, $expectedIdentifiers, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return normalized ISBN-10 and ISBN-13 equivalents where conversion is possible.
     *
     * @return list<string>
     */
    private function equivalentIsbns(string $isbn): array
    {
        $normalized = $this->normalizeIsbn($isbn);
        $identifiers = [$normalized];

        if (strlen($normalized) === 13 && str_starts_with($normalized, '978')) {
            $body = substr($normalized, 3, 9);
            $sum = 0;

            for ($index = 0; $index < 9; $index++) {
                $sum += (int) $body[$index] * (10 - $index);
            }

            $checkDigit = (11 - ($sum % 11)) % 11;
            $identifiers[] = $body.($checkDigit === 10 ? 'X' : (string) $checkDigit);
        } elseif (strlen($normalized) === 10) {
            $body = '978'.substr($normalized, 0, 9);
            $sum = 0;

            for ($index = 0; $index < 12; $index++) {
                $sum += (int) $body[$index] * ($index % 2 === 0 ? 1 : 3);
            }

            $identifiers[] = $body.((10 - ($sum % 10)) % 10);
        }

        return array_values(array_unique($identifiers));
    }

    /**
     * Remove separators and normalize the ISBN check digit.
     */
    private function normalizeIsbn(string $isbn): string
    {
        return strtoupper((string) preg_replace('/[^0-9X]/i', '', $isbn));
    }

    /**
     * Build the configured Google Books HTTP client.
     */
    private function client(): PendingRequest
    {
        return Http::baseUrl(config('services.google_books.base_url'))
            ->acceptJson()
            ->timeout(10);
    }
}
