<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhooks', function (Blueprint $table): void {
            $table->text('signing_secret')->nullable()->change();
        });

        DB::table('webhooks')->whereNotNull('signing_secret')->orderBy('id')->each(function (object $webhook): void {
            try {
                Crypt::decryptString($webhook->signing_secret);
            } catch (DecryptException) {
                DB::table('webhooks')->where('id', $webhook->id)->update([
                    'signing_secret' => Crypt::encryptString($webhook->signing_secret),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('webhooks')->whereNotNull('signing_secret')->orderBy('id')->each(function (object $webhook): void {
            try {
                $plainText = Crypt::decryptString($webhook->signing_secret);
                DB::table('webhooks')->where('id', $webhook->id)->update(['signing_secret' => $plainText]);
            } catch (DecryptException) {
                // Already plaintext; leave it unchanged.
            }
        });

        Schema::table('webhooks', function (Blueprint $table): void {
            $table->string('signing_secret')->nullable()->change();
        });
    }
};
