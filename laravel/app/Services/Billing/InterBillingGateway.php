<?php

namespace App\Services\Billing;

use App\Contracts\BillingGateway;
use App\Exceptions\InterApiException;
use App\Models\BillingCheckoutSession;
use App\Models\Subscription;
use Illuminate\Validation\ValidationException;

class InterBillingGateway implements BillingGateway
{
    public function __construct(private readonly InterPixAutomaticClient $client) {}

    public function configured(): bool
    {
        return $this->client->configured();
    }

    public function createCheckout(BillingCheckoutSession $checkout, array $payer = []): array
    {
        if ($checkout->payment_method !== 'pix') {
            throw ValidationException::withMessages([
                'payment_method' => 'O Banco Inter suporta a recorrência do Waflow por Pix Automático. Cartões exigem outro adquirente.',
            ]);
        }

        $name = trim((string) ($payer['name'] ?? ''));
        $taxId = preg_replace('/\D+/', '', (string) ($payer['tax_id'] ?? '')) ?: '';
        if ($name === '' || ! in_array(strlen($taxId), [11, 14], true)) {
            $errors = [];
            if ($name === '') {
                $errors['payer_name'] = 'Informe o nome do pagador.';
            }
            if (! in_array(strlen($taxId), [11, 14], true)) {
                $errors['payer_tax_id'] = 'Informe um CPF ou CNPJ válido.';
            }
            throw ValidationException::withMessages($errors);
        }

        $checkout->loadMissing('plan');
        $startsAt = now()->addMonthNoOverflow()->startOfDay();
        $location = $this->client->post('/locrec');
        $locationId = $location['id'] ?? null;
        if (! is_numeric($locationId)) {
            throw new InterApiException('O Banco Inter não retornou a location da recorrência.', 502, 'inter_location_invalid');
        }

        $documentKey = strlen($taxId) === 11 ? 'cpf' : 'cnpj';
        $recurrence = $this->client->post('/rec', [
            'vinculo' => [
                'contrato' => 'WAFLOW'.str_pad((string) $checkout->id, 12, '0', STR_PAD_LEFT),
                'devedor' => [$documentKey => $taxId, 'nome' => $name],
                'objeto' => 'Assinatura Waflow '.$checkout->plan->name,
            ],
            'calendario' => [
                'dataInicial' => $startsAt->toDateString(),
                'dataFinal' => $startsAt->copy()->addYears((int) config('billing.inter.recurrence_years', 5))->toDateString(),
                'periodicidade' => 'MENSAL',
            ],
            'valor' => ['valorRec' => number_format($checkout->amount_cents / 100, 2, '.', '')],
            'politicaRetentativa' => config('billing.inter.retry_policy', 'PERMITE_3R_7D'),
            'loc' => (int) $locationId,
        ]);
        $idRec = (string) ($recurrence['idRec'] ?? '');
        if ($idRec === '') {
            throw new InterApiException('O Banco Inter não retornou o identificador da recorrência.', 502, 'inter_recurrence_invalid');
        }

        $details = $this->client->get('/rec/'.$idRec);
        $pixCode = (string) ($details['pixCopiaECola'] ?? $recurrence['pixCopiaECola'] ?? '');
        $locationUrl = (string) data_get($details, 'loc.location', $location['location'] ?? '');
        $checkoutUrl = $locationUrl === '' ? null : (str_starts_with($locationUrl, 'http') ? $locationUrl : 'https://'.$locationUrl);

        return [
            'provider' => 'inter',
            'reference' => $idRec,
            'url' => $checkoutUrl,
            'payload' => [
                'integration_status' => 'awaiting_authorization',
                'journey' => 'JORNADA_2',
                'id_rec' => $idRec,
                'status' => $details['status'] ?? $recurrence['status'] ?? 'CRIADA',
                'location_id' => (int) $locationId,
                'location' => $locationUrl,
                'pix_copy_paste' => $pixCode !== '' ? $pixCode : null,
                'starts_at' => $startsAt->toDateString(),
                'payer_document_suffix' => substr($taxId, -4),
            ],
        ];
    }

    public function cancelCheckout(BillingCheckoutSession $checkout): void
    {
        if ($checkout->provider === 'inter' && filled($checkout->provider_reference)) {
            $this->client->patch('/rec/'.$checkout->provider_reference, ['status' => 'CANCELADA']);
        }
    }

    public function cancelSubscription(Subscription $subscription, bool $atPeriodEnd): void
    {
        if ($atPeriodEnd || blank($subscription->provider_subscription_id)) {
            return;
        }

        $this->client->patch('/rec/'.$subscription->provider_subscription_id, ['status' => 'CANCELADA']);
    }

    public function resumeSubscription(Subscription $subscription): void
    {
        // A recorrência permanece ativa no Inter durante o cancelamento agendado local.
    }
}
