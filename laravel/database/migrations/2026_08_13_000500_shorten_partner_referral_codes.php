<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $used = [];

        DB::table('partners')->orderBy('id')->get(['id', 'referral_code'])->each(function ($partner) use (&$used): void {
            $code = (string) $partner->referral_code;
            if (! preg_match('/^[A-Z0-9]{6}$/', $code) || isset($used[$code])) {
                do {
                    $code = Str::upper(Str::random(6));
                } while (isset($used[$code]) || DB::table('partners')->where('referral_code', $code)->exists());

                DB::table('partners')->where('id', $partner->id)->update(['referral_code' => $code]);
            }

            $used[$code] = true;
        });

        Schema::table('partners', function (Blueprint $table): void {
            $table->string('referral_code', 6)->change();
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table): void {
            $table->string('referral_code', 32)->change();
        });
    }
};
