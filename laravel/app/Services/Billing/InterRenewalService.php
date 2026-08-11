<?php

namespace App\Services\Billing;

use App\Exceptions\InterApiException;
use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InterRenewalService
{
    public function __construct(private readonly InterPixAutomaticClient $client) {}

    public function generateDueCharges(): int
    {
        if (config('billing.driver') !== 'inter' || ! $this->client->configured()) {
            return 0;
        }

        $receiver = $this->receiver();
        $subscriptionIds = Subscription::query()
            ->where('payment_provider', 'inter')
            ->whereNotNull('provider_subscription_id')
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->where('cancel_at_period_end', false)
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '<=', now()->addDays((int) config('billing.inter.charge_lead_days', 5)))
            ->pluck('id');

        $created = 0;
        foreach ($subscriptionIds as $subscriptionId) {
            try {
                $invoice = DB::transaction(function () use ($subscriptionId): ?Invoice {
                    $subscription = Subscription::query()->with(['workspace', 'plan'])->lockForUpdate()->find($subscriptionId);
                    if (! $subscription || $subscription->cancel_at_period_end || ! $subscription->current_period_ends_at) {
                        return null;
                    }

                    $dueAt = $subscription->current_period_ends_at->copy()->startOfDay();
                    if ($dueAt->isBefore(today())) {
                        $dueAt = today();
                    }
                    $number = sprintf('WF-%d-%s', $subscription->id, $dueAt->format('Ymd'));
                    $existing = Invoice::query()->where('number', $number)->first();
                    if ($existing && ! in_array($existing->status, ['failed', 'draft'], true)) {
                        return null;
                    }
                    if ($existing && $existing->updated_at?->isAfter(now()->subMinutes(5))) {
                        return null;
                    }

                    return $existing ?: $subscription->workspace->invoices()->create([
                        'subscription_id' => $subscription->id,
                        'number' => $number,
                        'status' => 'draft',
                        'amount_cents' => $subscription->plan->monthly_price_cents,
                        'currency' => config('billing.currency', 'BRL'),
                        'payment_method' => 'pix',
                        'provider' => 'inter',
                        'due_at' => $dueAt,
                        'metadata' => ['id_rec' => $subscription->provider_subscription_id],
                    ]);
                }, 3);
                if (! $invoice) {
                    continue;
                }

                $invoice->loadMissing('subscription.plan');
                $txid = substr(hash('sha256', 'waflow|'.$invoice->number), 0, 32);
                $response = $this->client->put('/cobr/'.$txid, [
                    'idRec' => $invoice->subscription->provider_subscription_id,
                    'infoAdicional' => 'Assinatura mensal Waflow - '.$invoice->subscription->plan->name,
                    'calendario' => ['dataDeVencimento' => $invoice->due_at->toDateString()],
                    'valor' => ['original' => number_format($invoice->amount_cents / 100, 2, '.', '')],
                    'ajusteDiaUtil' => true,
                    'recebedor' => $receiver,
                ]);

                DB::transaction(function () use ($invoice, $response, $txid): void {
                    $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
                    $locked->update([
                        'status' => 'pending',
                        'provider_reference' => (string) ($response['txid'] ?? $txid),
                        'metadata' => array_merge($locked->metadata ?? [], [
                            'provider_status' => $response['status'] ?? 'CRIADA',
                        ]),
                    ]);
                    $locked->workspace->payments()->updateOrCreate(
                        ['provider' => 'inter', 'provider_reference' => (string) ($response['txid'] ?? $txid)],
                        [
                            'invoice_id' => $locked->id,
                            'payment_method' => 'pix',
                            'status' => 'pending',
                            'amount_cents' => $locked->amount_cents,
                            'currency' => $locked->currency,
                            'metadata' => ['id_rec' => $locked->subscription->provider_subscription_id],
                        ],
                    );
                }, 3);
                $created++;
            } catch (\Throwable $exception) {
                if (isset($invoice) && $invoice) {
                    $invoice->update([
                        'status' => 'failed',
                        'metadata' => array_merge($invoice->metadata ?? [], ['provider_error' => $exception->getMessage()]),
                    ]);
                }
                Log::error('Falha ao gerar renovação via Banco Inter.', [
                    'subscription_id' => $subscriptionId,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $created;
    }

    /** @return array{nome: string, cnpj: string, agencia: string, conta: string, tipoConta: string} */
    private function receiver(): array
    {
        $config = config('billing.inter.receiver', []);
        $receiver = [
            'nome' => trim((string) ($config['name'] ?? '')),
            'cnpj' => preg_replace('/\D+/', '', (string) ($config['cnpj'] ?? '')) ?: '',
            'agencia' => preg_replace('/\D+/', '', (string) ($config['agency'] ?? '')) ?: '',
            'conta' => preg_replace('/\D+/', '', (string) ($config['account'] ?? '')) ?: '',
            'tipoConta' => trim((string) ($config['account_type'] ?? 'CORRENTE')),
        ];
        if (in_array('', $receiver, true)) {
            throw new InterApiException('Preencha os dados INTER_RECEIVER_* para emitir cobranças recorrentes.', 503, 'inter_receiver_not_configured');
        }

        return $receiver;
    }
}
