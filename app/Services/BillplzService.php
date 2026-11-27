<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class BillplzService
{
    protected Client $client;

    protected string $apiKey;

    protected string $collectionId;

    protected string $xSignatureKey;

    protected string $baseUrl;

    protected bool $sandbox;

    public function __construct()
    {
        $this->apiKey = config('services.billplz.api_key');
        $this->collectionId = config('services.billplz.collection_id');
        $this->xSignatureKey = config('services.billplz.x_signature_key');
        $this->baseUrl = config('services.billplz.url');
        $this->sandbox = config('services.billplz.sandbox');

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'auth' => [$this->apiKey, ''], // API key as username, no password
            'headers' => [
                'Accept' => 'application/json',
            ],
            'verify' => ! $this->sandbox, // Disable SSL verification in sandbox
        ]);
    }

    /**
     * Create a new bill for payroll payment
     *
     * @param  array  $data  Bill data
     */
    public function createBill(array $data): ?array
    {
        try {
            $formParams = [
                'collection_id' => $this->collectionId,
                'email' => $data['email'],
                'name' => $data['name'],
                'amount' => $this->convertToSen($data['amount']), // Convert RM to sen (cents)
                'callback_url' => $data['callback_url'],
                'redirect_url' => $data['redirect_url'] ?? $data['callback_url'],
                'description' => $data['description'] ?? 'Payroll Payment',
                'reference_1_label' => $data['reference_1_label'] ?? 'Payroll ID',
                'reference_1' => $data['reference_1'] ?? '',
            ];

            // Add due_at (expiry date) if provided - format: YYYY-MM-DD
            // Bills will expire after this date and cannot be paid
            if (! empty($data['due_at'])) {
                $formParams['due_at'] = $data['due_at'];
            }

            $response = $this->client->post('bills', [
                'form_params' => $formParams,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            Log::info('Billplz bill created successfully', [
                'bill_id' => $result['id'] ?? null,
                'url' => $result['url'] ?? null,
                'due_at' => $data['due_at'] ?? 'no expiry',
            ]);

            return $result;
        } catch (GuzzleException $e) {
            Log::error('Billplz bill creation failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return null;
        }
    }

    /**
     * Get bill details
     */
    public function getBill(string $billId): ?array
    {
        try {
            $response = $this->client->get("bills/{$billId}");

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error('Billplz get bill failed', [
                'bill_id' => $billId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Delete a bill
     */
    public function deleteBill(string $billId): bool
    {
        try {
            $this->client->delete("bills/{$billId}");

            Log::info('Billplz bill deleted successfully', [
                'bill_id' => $billId,
            ]);

            return true;
        } catch (GuzzleException $e) {
            Log::warning('Billplz bill deletion failed', [
                'bill_id' => $billId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Make sure a bill can no longer be paid, before a replacement is issued.
     *
     * Returns ['status' => ..., 'bill' => ?array] where status is:
     *  - 'closed'  the bill was deleted now, or was already gone
     *  - 'paid'    money was taken on it, so it must not be replaced
     *  - 'unknown' Billplz could not be reached; its state is not known
     */
    public function closeUnpaidBill(string $billId): array
    {
        try {
            $bill = json_decode($this->client->get("bills/{$billId}")->getBody()->getContents(), true);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return ['status' => 'closed', 'bill' => null];
            }

            Log::warning('Billplz bill lookup failed while closing bill', ['bill_id' => $billId, 'error' => $e->getMessage()]);

            return ['status' => 'unknown', 'bill' => null];
        } catch (GuzzleException $e) {
            Log::warning('Billplz bill lookup failed while closing bill', ['bill_id' => $billId, 'error' => $e->getMessage()]);

            return ['status' => 'unknown', 'bill' => null];
        }

        if (($bill['paid'] ?? false) === true || ($bill['state'] ?? null) === 'paid') {
            return ['status' => 'paid', 'bill' => $bill];
        }

        if (($bill['state'] ?? null) === 'deleted') {
            return ['status' => 'closed', 'bill' => $bill];
        }

        try {
            $this->client->delete("bills/{$billId}");
            Log::info('Billplz bill closed before issuing a new one', ['bill_id' => $billId]);

            return ['status' => 'closed', 'bill' => $bill];
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return ['status' => 'closed', 'bill' => $bill];
            }

            Log::warning('Billplz bill could not be closed', ['bill_id' => $billId, 'error' => $e->getMessage()]);

            return ['status' => 'unknown', 'bill' => $bill];
        } catch (GuzzleException $e) {
            Log::warning('Billplz bill could not be closed', ['bill_id' => $billId, 'error' => $e->getMessage()]);

            return ['status' => 'unknown', 'bill' => $bill];
        }
    }

    /**
     * Validate webhook signature
     */
    public function validateSignature(array $params, string $xSignature): bool
    {
        if ($xSignature === '' || empty($this->xSignatureKey)) {
            return false;
        }

        // Billplz X Signature: every key+value pair (except x_signature) as
        // "keyvalue", sorted ascending case-insensitively, joined with "|",
        // then HMAC-SHA256 with the X Signature key.
        $pairs = [];
        foreach ($params as $key => $value) {
            if ($key === 'x_signature' || is_array($value)) {
                continue;
            }
            $pairs[] = $key.(is_bool($value) ? ($value ? 'true' : 'false') : (string) $value);
        }

        usort($pairs, 'strcasecmp');

        $computedSignature = hash_hmac('sha256', implode('|', $pairs), $this->xSignatureKey);

        return hash_equals($computedSignature, $xSignature);
    }

    /**
     * Whether a bill fetched from the Billplz API is paid, and paid at least the
     * amount we billed. Only the API response is trusted, never callback input.
     */
    public function isBillPaidInFull(array $bill, float $expectedAmount): bool
    {
        if (($bill['paid'] ?? false) !== true || ($bill['state'] ?? null) !== 'paid') {
            return false;
        }

        $paidSen = (int) ($bill['paid_amount'] ?? $bill['amount'] ?? 0);

        return $paidSen >= $this->convertToSen($expectedAmount);
    }

    /**
     * Convert RM amount to sen (cents)
     * RM 1,721.25 = 172125 sen
     */
    protected function convertToSen(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Convert sen to RM amount
     */
    public function convertToRinggit(int $sen): float
    {
        return $sen / 100;
    }

    /**
     * Get payment URL with auto-submit for direct gateway
     */
    public function getDirectPaymentUrl(string $billUrl): string
    {
        return $billUrl.'?auto_submit=true';
    }

    /**
     * Create a collection (one-time setup)
     */
    public function createCollection(string $title): ?array
    {
        try {
            $response = $this->client->post('collections', [
                'form_params' => [
                    'title' => $title,
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error('Billplz collection creation failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check if sandbox mode is enabled
     */
    public function isSandbox(): bool
    {
        return $this->sandbox;
    }
}
