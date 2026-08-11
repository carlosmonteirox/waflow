<?php

namespace App\Services\Billing;

use App\Models\BillingCheckoutSession;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class InterWebhookService
{
    /** @param array<string, mixed> $payload */
    public function handle(string $event, array $payload): int
    {
        return match ($event) {
            'rec' => $this->handleRecurrences($payload),
            'cobr' => $this->handleCharges($payload),
            default => 0,
        };
    }

    /** @param array<string, mixed> $payload */
    private function handleRecurrences(array $payload): int
    {
        $items = $this->items($payload, ['recs', 'recorrencias', 'rec']);
        $processed = 0;
        foreach ($items as $item) {
            $idRec = (string) ($item['idRec'] ?? '');
            if ($idRec === '') {
                continue;
            }

            DB::transaction(function () use ($idRec, $item, &$processed): void {
                $checkout = BillingCheckoutSession::query()->where('provider', 'inter')
                    ->where('provider_reference', $idRec)->lockForUpdate()->first();
                if (! $checkout) {
                    return;
                }

                $providerStatus = strtoupper((string) ($item['status'] ?? ''));
                $status = match ($providerStatus) {
                    'APROVADA' => 'completed',
                    'REJEITADA', 'CANCELADA', 'ENCERRADA' => 'failed',
                    default => $checkout->status,
                };
                $checkout->update([
                    'status' => $status,
                    'completed_at' => $status === 'completed' ? ($checkout->completed_at ?? now()) : $checkout->completed_at,
                    'provider_payload' => array_merge($checkout->provider_payload ?? [], ['status' => $providerStatus]),
                ]);

                if ($status === 'completed') {
                    $startsAt = data_get($checkout->provider_payload, 'starts_at');
                    $subscription = $checkout->workspace->subscription()->lockForUpdate()->firstOrFail();
                    $subscription->update([
                        'plan_id' => $checkout->plan_id,
                        'payment_provider' => 'inter',
                        'provider_subscription_id' => $idRec,
                        'cancel_at_period_end' => false,
                        'cancelled_at' => null,
                        'current_period_ends_at' => $startsAt ?: now()->addMonthNoOverflow(),
                    ]);
                }
                $processed++;
            }, 3);
        }

        return $processed;
    }

    /** @param array<string, mixed> $payload */
    private function handleCharges(array $payload): int
    {
        $items = $this->items($payload, ['cobrs', 'cobrancas', 'cobr']);
        $processed = 0;
        foreach ($items as $item) {
            $txid = (string) ($item['txid'] ?? $item['txId'] ?? '');
            if ($txid === '') {
                continue;
            }

            DB::transaction(function () use ($txid, $item, &$processed): void {
                $invoice = Invoice::query()->where('provider', 'inter')->where('provider_reference', $txid)
                    ->lockForUpdate()->first();
                if (! $invoice) {
                    return;
                }

                $providerStatus = strtoupper((string) ($item['status'] ?? ''));
                $paid = $providerStatus === 'CONCLUIDA';
                $invoiceStatus = match ($providerStatus) {
                    'CONCLUIDA' => 'paid',
                    'EXPIRADA' => 'overdue',
                    'REJEITADA' => 'failed',
                    'CANCELADA' => 'void',
                    default => 'pending',
                };
                $invoice->update([
                    'status' => $invoiceStatus,
                    'paid_at' => $paid ? ($invoice->paid_at ?? now()) : $invoice->paid_at,
                    'metadata' => array_merge($invoice->metadata ?? [], ['provider_status' => $providerStatus]),
                ]);

                $payment = $invoice->payments()->where('provider_reference', $txid)->lockForUpdate()->first();
                $payment?->update([
                    'status' => match ($providerStatus) {
                        'CONCLUIDA' => 'paid',
                        'CANCELADA' => 'cancelled',
                        'EXPIRADA', 'REJEITADA' => 'failed',
                        default => 'processing',
                    },
                    'paid_at' => $paid ? ($payment->paid_at ?? now()) : $payment->paid_at,
                    'metadata' => array_merge($payment->metadata ?? [], ['provider_status' => $providerStatus]),
                ]);

                if ($invoice->subscription) {
                    $invoice->subscription->update($paid ? [
                        'status' => 'active',
                        'current_period_ends_at' => $invoice->due_at->copy()->addMonthNoOverflow(),
                    ] : ($invoiceStatus === 'overdue' || $invoiceStatus === 'failed' ? ['status' => 'past_due'] : []));
                }
                $processed++;
            }, 3);
        }

        return $processed;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $keys
     * @return array<int, array<string, mixed>>
     */
    private function items(array $payload, array $keys): array
    {
        foreach ($keys as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $value = $payload[$key];
                if (array_is_list($value)) {
                    return array_values(array_filter($value, 'is_array'));
                }

                return [$value];
            }
        }

        return isset($payload['idRec']) || isset($payload['txid']) || isset($payload['txId']) ? [$payload] : [];
    }
}
