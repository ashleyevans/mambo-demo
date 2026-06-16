<?php

namespace App\Http\Controllers;

use App\Models\BankTransaction;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionExportController extends Controller
{
    /**
     * The flat columns written to the CSV export.
     *
     * @var array<int, string>
     */
    private const CSV_COLUMNS = [
        'truelayer_transaction_id',
        'status',
        'normalised_provider_transaction_id',
        'provider_transaction_id',
        'booked_at',
        'description',
        'merchant_name',
        'amount',
        'currency',
        'transaction_type',
        'transaction_category',
        'transaction_classification',
        'running_balance',
        'running_balance_currency',
        'bank',
        'account',
        'meta',
    ];

    /**
     * Stream every transaction as a CSV file.
     */
    public function csv(Request $request): StreamedResponse
    {
        $user = $request->user();

        return response()->streamDownload(function () use ($user): void {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, self::CSV_COLUMNS);

            $user->bankTransactions()
                ->with('account.connection:id,provider_name')
                ->orderByDesc('booked_at')
                ->chunk(500, function ($transactions) use ($handle): void {
                    foreach ($transactions as $transaction) {
                        fputcsv($handle, $this->csvRow($transaction));
                    }
                });

            fclose($handle);
        }, 'transactions.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Stream every transaction as JSON, including the full raw TrueLayer payload.
     */
    public function json(Request $request): StreamedResponse
    {
        $user = $request->user();

        return response()->streamDownload(function () use ($user): void {
            echo '[';
            $first = true;

            $user->bankTransactions()
                ->with('account.connection:id,provider_name')
                ->orderByDesc('booked_at')
                ->chunk(500, function ($transactions) use (&$first): void {
                    foreach ($transactions as $transaction) {
                        echo $first ? '' : ',';
                        $first = false;

                        echo json_encode($this->jsonEntry($transaction));
                    }
                });

            echo ']';
        }, 'transactions.json', ['Content-Type' => 'application/json']);
    }

    /**
     * Download a single transaction as CSV.
     */
    public function csvForTransaction(Request $request, BankTransaction $transaction): StreamedResponse
    {
        abort_unless($transaction->user_id === $request->user()->id, 403);

        $transaction->load('account.connection:id,provider_name');

        return response()->streamDownload(function () use ($transaction): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, self::CSV_COLUMNS);
            fputcsv($handle, $this->csvRow($transaction));
            fclose($handle);
        }, "transaction-{$transaction->id}.csv", ['Content-Type' => 'text/csv']);
    }

    /**
     * Download a single transaction as JSON, including the full raw payload.
     */
    public function jsonForTransaction(Request $request, BankTransaction $transaction): StreamedResponse
    {
        abort_unless($transaction->user_id === $request->user()->id, 403);

        $transaction->load('account.connection:id,provider_name');

        return response()->streamDownload(function () use ($transaction): void {
            echo json_encode($this->jsonEntry($transaction), JSON_PRETTY_PRINT);
        }, "transaction-{$transaction->id}.json", ['Content-Type' => 'application/json']);
    }

    /**
     * Build the flat CSV row for a transaction.
     *
     * @return array<int, string|null>
     */
    private function csvRow(BankTransaction $transaction): array
    {
        return [
            $transaction->truelayer_transaction_id,
            $transaction->status,
            $transaction->normalised_provider_transaction_id,
            $transaction->provider_transaction_id,
            $transaction->booked_at?->toIso8601String(),
            $transaction->description,
            $transaction->merchant_name,
            $transaction->amount,
            $transaction->currency,
            $transaction->transaction_type,
            $transaction->transaction_category,
            $this->encodeList($transaction->transaction_classification),
            $transaction->running_balance,
            $transaction->running_balance_currency,
            $transaction->account?->connection?->provider_name,
            $transaction->account?->display_name,
            $transaction->meta ? json_encode($transaction->meta) : null,
        ];
    }

    /**
     * Build the JSON entry for a transaction (raw payload plus context).
     *
     * @return array<string, mixed>
     */
    private function jsonEntry(BankTransaction $transaction): array
    {
        return [
            'bank' => $transaction->account?->connection?->provider_name,
            'account' => $transaction->account?->display_name,
            'transaction' => $transaction->raw,
        ];
    }

    /**
     * @param  array<int, string>|null  $list
     */
    private function encodeList(?array $list): ?string
    {
        return $list ? implode(' > ', $list) : null;
    }
}
