<?php

namespace App\Services;

use App\Models\TwoFactorCode;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TwoFactorService
{
    public function issue(User $user, Request $request, bool $enforceCooldown = false): TwoFactorCode
    {
        $plainCode = (string) random_int(100000, 999999);
        $code = DB::transaction(function () use ($user, $request, $plainCode, $enforceCooldown): TwoFactorCode {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $latest = $user->twoFactorCodes()->latest()->first();
            if ($enforceCooldown && $latest?->created_at?->isAfter(now()->subMinute())) {
                throw ValidationException::withMessages(['code' => 'Aguarde um minuto antes de solicitar outro código.']);
            }
            $user->twoFactorCodes()->whereNull('consumed_at')->update(['consumed_at' => now()]);

            return $user->twoFactorCodes()->create([
                'code_hash' => $this->hash($user, $plainCode),
                'request_ip_hash' => $request->ip()
                    ? hash_hmac('sha256', $request->ip(), (string) config('app.key'))
                    : null,
                'expires_at' => now()->addMinutes(5),
            ]);
        });

        $user->notify(new TwoFactorCodeNotification($plainCode));

        return $code;
    }

    public function verify(User $user, string $plainCode): bool
    {
        return DB::transaction(function () use ($user, $plainCode): bool {
            $code = $user->twoFactorCodes()->whereNull('consumed_at')->lockForUpdate()->latest()->first();
            if (! $code || $code->expires_at->isPast()) {
                throw ValidationException::withMessages(['verification_code' => 'O código expirou. Solicite um novo código.']);
            }
            if ($code->attempts >= 5) {
                throw ValidationException::withMessages(['verification_code' => 'O limite de tentativas foi atingido. Solicite um novo código.']);
            }

            if (! hash_equals($code->code_hash, $this->hash($user, $plainCode))) {
                $code->increment('attempts');
                if ($code->fresh()->attempts >= 5) {
                    $code->update(['consumed_at' => now()]);
                }

                return false;
            }

            $code->update(['consumed_at' => now()]);
            $user->update(['last_two_factor_at' => now()]);

            return true;
        }, 3);
    }

    private function hash(User $user, string $code): string
    {
        return hash_hmac('sha256', $user->id.'|'.$code, (string) config('app.key'));
    }
}
