<?php

namespace App\Console\Commands;

use App\Services\Billing\InterPixAutomaticClient;
use Illuminate\Console\Command;

class ConfigureInterWebhooks extends Command
{
    protected $signature = 'billing:inter:configure-webhooks';

    protected $description = 'Cadastra os callbacks de recorrências e cobranças recorrentes no Banco Inter';

    public function handle(InterPixAutomaticClient $client): int
    {
        $baseUrl = $client->webhookBaseUrl();
        $client->put('/webhookrec', ['webhookUrl' => $baseUrl]);
        $client->put('/webhookcobr', ['webhookUrl' => $baseUrl]);
        $this->info('Webhooks do Banco Inter configurados em '.$baseUrl.'/rec e '.$baseUrl.'/cobr.');

        return self::SUCCESS;
    }
}
