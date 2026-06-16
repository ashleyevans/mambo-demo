<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OneSignal
{
    /**
     * Determine whether OneSignal credentials are configured.
     */
    public function isConfigured(): bool
    {
        return filled(config('services.onesignal.app_id'))
            && filled(config('services.onesignal.rest_api_key'));
    }

    /**
     * Send a push notification to the app's subscribers.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ConnectionException
     */
    public function send(string $title, string $message, array $data = [], ?string $imageUrl = null, ?string $subtitle = null): void
    {
        $payload = [
            'app_id' => config('services.onesignal.app_id'),
            'included_segments' => [config('services.onesignal.segment', 'Subscribed Users')],
            'headings' => ['en' => $title],
            'contents' => ['en' => $message],
            'data' => $data,
        ];

        if ($subtitle !== null) {
            $payload['subtitle'] = ['en' => $subtitle];
        }

        if ($imageUrl !== null) {
            $payload['large_icon'] = $imageUrl;          // Android
            $payload['ios_attachments'] = ['logo' => $imageUrl]; // iOS
            $payload['chrome_web_icon'] = $imageUrl;     // Web
        }

        Http::withHeaders([
            'Authorization' => $this->authorizationHeader(),
        ])->post('https://api.onesignal.com/notifications', $payload)->throw();
    }

    /**
     * Build the Authorization header. New "os_v2_" keys use the "Key" scheme;
     * legacy REST API keys use "Basic".
     */
    private function authorizationHeader(): string
    {
        $key = (string) config('services.onesignal.rest_api_key');
        $scheme = str_starts_with($key, 'os_v2_') ? 'Key' : 'Basic';

        return "{$scheme} {$key}";
    }
}
