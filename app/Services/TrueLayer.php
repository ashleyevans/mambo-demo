<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class TrueLayer
{
    /**
     * Determine whether the TrueLayer integration has been configured.
     */
    public function isConfigured(): bool
    {
        return filled(config('services.truelayer.client_id'))
            && filled(config('services.truelayer.client_secret'));
    }

    /**
     * Build the URL that begins the TrueLayer consent flow.
     */
    public function authorizationUrl(string $state): string
    {
        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.truelayer.client_id'),
            'scope' => config('services.truelayer.scopes'),
            'redirect_uri' => config('services.truelayer.redirect'),
            'providers' => config('services.truelayer.providers'),
            'state' => $state,
        ]);

        return $this->authBaseUrl().'/?'.$query;
    }

    /**
     * Exchange an authorization code for an access/refresh token pair.
     *
     * @return array{access_token: string, refresh_token: ?string, expires_in: int}
     *
     * @throws ConnectionException
     */
    public function exchangeCode(string $code): array
    {
        return Http::asForm()
            ->post($this->authBaseUrl().'/connect/token', [
                'grant_type' => 'authorization_code',
                'client_id' => config('services.truelayer.client_id'),
                'client_secret' => config('services.truelayer.client_secret'),
                'redirect_uri' => config('services.truelayer.redirect'),
                'code' => $code,
            ])
            ->throw()
            ->json();
    }

    /**
     * Exchange a refresh token for a fresh access token.
     *
     * @return array{access_token: string, refresh_token: ?string, expires_in: int}
     *
     * @throws ConnectionException
     */
    public function refreshToken(string $refreshToken): array
    {
        return Http::asForm()
            ->post($this->authBaseUrl().'/connect/token', [
                'grant_type' => 'refresh_token',
                'client_id' => config('services.truelayer.client_id'),
                'client_secret' => config('services.truelayer.client_secret'),
                'refresh_token' => $refreshToken,
            ])
            ->throw()
            ->json();
    }

    /**
     * Fetch the accounts available for the given access token.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws ConnectionException
     */
    public function accounts(string $accessToken): array
    {
        return $this->fetchResults($accessToken, $this->apiBaseUrl().'/data/v1/accounts');
    }

    /**
     * Fetch the balance for a single account.
     *
     * @return array<string, mixed>|null
     *
     * @throws ConnectionException
     */
    public function balance(string $accessToken, string $accountId): ?array
    {
        return $this->fetchResults($accessToken, $this->apiBaseUrl()."/data/v1/accounts/{$accountId}/balance")[0] ?? null;
    }

    /**
     * Fetch the transactions for a single account.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws ConnectionException
     */
    public function transactions(string $accessToken, string $accountId): array
    {
        return $this->fetchResults($accessToken, $this->apiBaseUrl()."/data/v1/accounts/{$accountId}/transactions");
    }

    /**
     * Fetch the pending (not yet settled) transactions for a single account.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws ConnectionException
     */
    public function pendingTransactions(string $accessToken, string $accountId): array
    {
        return $this->fetchResults($accessToken, $this->apiBaseUrl()."/data/v1/accounts/{$accountId}/transactions/pending");
    }

    /**
     * Call a Data API endpoint, honouring TrueLayer's async results envelope.
     *
     * Responses carry a `status` of Succeeded, Queued, Running or Failed. When
     * the data is still being fetched from the bank we poll briefly; a Failed
     * status raises so the sync reports it.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws ConnectionException
     */
    private function fetchResults(string $accessToken, string $url): array
    {
        $body = [];

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $body = Http::withToken($accessToken)->get($url)->throw()->json();

            $status = $body['status'] ?? 'Succeeded';

            if ($status === 'Succeeded') {
                return $body['results'] ?? [];
            }

            if ($status === 'Failed') {
                throw new \RuntimeException("TrueLayer request failed for {$url}");
            }

            // Queued or Running: the bank data is still being retrieved.
            if ($attempt < 3) {
                usleep(1_000_000);
            }
        }

        return $body['results'] ?? [];
    }

    /**
     * The TrueLayer authentication host for the configured environment.
     */
    protected function authBaseUrl(): string
    {
        return $this->isSandbox()
            ? 'https://auth.truelayer-sandbox.com'
            : 'https://auth.truelayer.com';
    }

    /**
     * The TrueLayer Data API host for the configured environment.
     */
    protected function apiBaseUrl(): string
    {
        return $this->isSandbox()
            ? 'https://api.truelayer-sandbox.com'
            : 'https://api.truelayer.com';
    }

    /**
     * Determine whether the sandbox environment is in use.
     */
    protected function isSandbox(): bool
    {
        return config('services.truelayer.environment', 'sandbox') !== 'live';
    }
}
