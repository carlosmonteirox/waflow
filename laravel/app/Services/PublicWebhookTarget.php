<?php

namespace App\Services;

use InvalidArgumentException;

class PublicWebhookTarget
{
    /** @return array{host: string, port: int, addresses: array<int, string>} */
    public function inspect(string $url, bool $resolveDns = true): array
    {
        $parts = parse_url($url);
        if (! is_array($parts) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('A URL do webhook é inválida.');
        }

        $scheme = mb_strtolower((string) ($parts['scheme'] ?? ''));
        $host = trim(mb_strtolower((string) ($parts['host'] ?? '')), '[]');
        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('A URL do webhook deve apontar para um endereço HTTP público sem credenciais embutidas.');
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (($scheme === 'https' && $port !== 443) || ($scheme === 'http' && $port !== 80)) {
            throw new InvalidArgumentException('A URL do webhook deve usar as portas padrão 80 ou 443.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $this->assertPublicIp($host);

            return ['host' => $host, 'port' => $port, 'addresses' => [$host]];
        }

        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
            || ! str_contains($host, '.')
            || $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')) {
            throw new InvalidArgumentException('O webhook deve usar um domínio público válido.');
        }

        if (! $resolveDns) {
            return ['host' => $host, 'port' => $port, 'addresses' => []];
        }

        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        $addresses = [];
        foreach (is_array($records) ? $records : [] as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($address)) {
                $this->assertPublicIp($address);
                $addresses[] = $address;
            }
        }

        $addresses = array_values(array_unique($addresses));
        if ($addresses === []) {
            throw new InvalidArgumentException('O domínio do webhook não possui endereços públicos válidos.');
        }

        return ['host' => $host, 'port' => $port, 'addresses' => $addresses];
    }

    /** @return array<string, mixed> */
    public function requestOptions(string $url, bool $resolveDns = true): array
    {
        $target = $this->inspect($url, $resolveDns);
        if ($target['addresses'] === [] || ! defined('CURLOPT_RESOLVE')) {
            return ['allow_redirects' => false];
        }

        $address = $target['addresses'][0];
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $address = '['.$address.']';
        }

        return [
            'allow_redirects' => false,
            'curl' => [CURLOPT_RESOLVE => [$target['host'].':'.$target['port'].':'.$address]],
        ];
    }

    private function assertPublicIp(string $address): void
    {
        if (filter_var($address, FILTER_VALIDATE_IP) === false
            || filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new InvalidArgumentException('A URL do webhook não pode apontar para uma rede privada ou reservada.');
        }
    }
}
