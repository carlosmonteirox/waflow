<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PublicWebhookUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parts = is_string($value) ? parse_url($value) : false;
        $host = is_array($parts) ? mb_strtolower((string) ($parts['host'] ?? '')) : '';
        $scheme = is_array($parts) ? ($parts['scheme'] ?? '') : '';
        if (! in_array($scheme, ['http', 'https'], true) || ! $host || in_array($host, ['localhost', 'gateway', 'laravel', 'openwa'], true) || str_ends_with($host, '.local')) {
            $fail('A URL do webhook deve apontar para um endereço HTTP público.');

            return;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            $fail('A URL do webhook não pode apontar para uma rede privada.');

            return;
        }

        if (! app()->environment('testing') && ! filter_var($host, FILTER_VALIDATE_IP)) {
            $addresses = gethostbynamel($host) ?: [];
            if ($addresses === [] || collect($addresses)->contains(fn ($ip) => ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
                $fail('O domínio do webhook deve resolver apenas para endereços públicos.');
            }
        }
    }
}
