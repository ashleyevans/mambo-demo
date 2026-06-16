<?php

namespace App\Http\Controllers;

use App\Actions\SyncBankConnection;
use App\Models\BankConnection;
use App\Services\TrueLayer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct(
        private readonly TrueLayer $trueLayer,
        private readonly SyncBankConnection $syncConnection,
    ) {}

    /**
     * Display the user's connected bank accounts.
     */
    public function index(Request $request): Response
    {
        $connections = $request->user()
            ->bankConnections()
            ->with('accounts')
            ->latest()
            ->get();

        return Inertia::render('accounts/index', [
            'connections' => $connections,
            'trueLayerConfigured' => $this->trueLayer->isConfigured(),
        ]);
    }

    /**
     * Begin the TrueLayer consent flow.
     */
    public function connect(Request $request): RedirectResponse
    {
        if (! $this->trueLayer->isConfigured()) {
            return redirect()->route('accounts.index')
                ->with('error', 'Open banking is not configured yet. Add your TrueLayer credentials to connect an account.');
        }

        $state = Str::random(40);
        $request->session()->put('truelayer_state', $state);

        return redirect()->away($this->trueLayer->authorizationUrl($state));
    }

    /**
     * Handle the redirect back from TrueLayer and store the linked accounts.
     */
    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()->route('accounts.index')
                ->with('error', 'The bank connection was cancelled.');
        }

        $expectedState = $request->session()->pull('truelayer_state');

        if (! $request->filled('code') || ! $expectedState || ! hash_equals($expectedState, (string) $request->query('state'))) {
            return redirect()->route('accounts.index')
                ->with('error', 'We could not verify the bank connection. Please try again.');
        }

        try {
            $tokens = $this->trueLayer->exchangeCode($request->query('code'));

            $connection = $request->user()->bankConnections()->create([
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? null,
                'token_expires_at' => isset($tokens['expires_in'])
                    ? Carbon::now()->addSeconds((int) $tokens['expires_in'])
                    : null,
                'consent_expires_at' => Carbon::now()->addDays((int) config('services.truelayer.consent_days', 90)),
                'status' => 'active',
            ]);

            ($this->syncConnection)($connection);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('accounts.index')
                ->with('error', 'We were unable to retrieve your accounts from TrueLayer.');
        }

        return redirect()->route('accounts.index')
            ->with('success', 'Bank account connected successfully.');
    }

    /**
     * Re-sync a connection on demand using its stored offline access token.
     */
    public function refresh(Request $request, BankConnection $connection): RedirectResponse
    {
        abort_unless($connection->user_id === $request->user()->id, 403);

        if ($connection->consentHasExpired()) {
            return redirect()->route('accounts.index')
                ->with('error', 'Your consent for this bank has expired. Please reconnect it.');
        }

        try {
            ($this->syncConnection)($connection);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('accounts.index')
                ->with('error', 'We were unable to refresh this account.');
        }

        return redirect()->route('accounts.index')
            ->with('success', 'Account data refreshed.');
    }

    /**
     * Disconnect a linked bank connection and remove its accounts.
     */
    public function destroy(Request $request, BankConnection $connection): RedirectResponse
    {
        abort_unless($connection->user_id === $request->user()->id, 403);

        $connection->delete();

        return redirect()->route('accounts.index')
            ->with('success', 'Bank account disconnected.');
    }
}
