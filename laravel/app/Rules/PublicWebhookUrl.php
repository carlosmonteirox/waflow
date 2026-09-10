<?php

namespace App\Rules;

use App\Services\PublicWebhookTarget;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

class PublicWebhookUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            app(PublicWebhookTarget::class)->inspect((string) $value, ! app()->environment('testing'));
        } catch (InvalidArgumentException $exception) {
            $fail($exception->getMessage());
        }
    }
}
