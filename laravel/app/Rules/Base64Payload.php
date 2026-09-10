<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Base64Payload implements ValidationRule
{
    public function __construct(private readonly int $maxDecodedBytes) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('O conteúdo em base64 é inválido.');

            return;
        }

        $payload = preg_match('/^data:[a-z0-9.+-]+\/[a-z0-9.+-]+;base64,/i', $value) === 1
            ? (string) substr($value, strpos($value, ',') + 1)
            : $value;
        $length = strlen($payload);
        $padding = str_ends_with($payload, '==') ? 2 : (str_ends_with($payload, '=') ? 1 : 0);
        $decodedBytes = intdiv($length, 4) * 3 - $padding;

        if ($length === 0 || $length % 4 !== 0 || preg_match('/^[A-Za-z0-9+\/]*={0,2}$/D', $payload) !== 1) {
            $fail('O conteúdo em base64 é inválido.');

            return;
        }

        if ($decodedBytes > $this->maxDecodedBytes) {
            $fail('O arquivo codificado excede o limite de '.number_format($this->maxDecodedBytes / 1048576, 0, ',', '.').' MB.');
        }
    }
}
