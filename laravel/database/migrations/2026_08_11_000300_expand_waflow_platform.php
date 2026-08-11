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
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });
        DB::table('workspaces')->whereNull('uuid')->orderBy('id')->eachById(function ($workspace): void {
            DB::table('workspaces')->where('id', $workspace->id)->update(['uuid' => (string) Str::uuid()]);
        });

        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedInteger('portal_limit')->default(1)->after('api_key_limit');
        });
        DB::table('plans')->where('slug', 'growth')->update(['portal_limit' => 10]);

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('two_factor_enabled')->default(true);
            $table->timestamp('last_two_factor_at')->nullable();
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('payment_provider')->nullable();
            $table->string('provider_customer_id')->nullable()->index();
            $table->string('provider_subscription_id')->nullable()->index();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('cancelled_at')->nullable();
        });

        Schema::table('flows', function (Blueprint $table): void {
            $table->unsignedInteger('run_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->text('last_error')->nullable();
        });

        Schema::table('webhook_deliveries', function (Blueprint $table): void {
            $table->string('idempotency_key', 191)->nullable();
            $table->json('payload')->nullable();
            $table->unique(['webhook_id', 'idempotency_key']);
        });

        Schema::create('two_factor_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash', 64);
            $table->string('request_ip_hash', 64)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'consumed_at']);
        });

        Schema::create('workspace_invitations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email')->index();
            $table->string('role', 40)->default('member');
            $table->string('token_hash', 64)->unique();
            $table->string('status', 30)->default('pending')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'email', 'status']);
        });

        Schema::create('billing_checkout_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_method', 30);
            $table->string('status', 30)->default('draft')->index();
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable()->index();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('BRL');
            $table->string('checkout_url', 2048)->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number')->unique();
            $table->string('status', 30)->default('open')->index();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('BRL');
            $table->string('payment_method', 30)->nullable();
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable()->index();
            $table->string('payment_url', 2048)->nullable();
            $table->text('pix_code')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('billing_checkout_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_method', 30);
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('BRL');
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable()->index();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('flow_executions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flow_id')->constrained()->cascadeOnDelete();
            $table->string('source_key', 191);
            $table->string('trigger_type', 80)->index();
            $table->string('status', 30)->default('queued')->index();
            $table->json('trigger_payload')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['flow_id', 'source_key']);
            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_executions');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('billing_checkout_sessions');
        Schema::dropIfExists('workspace_invitations');
        Schema::dropIfExists('two_factor_codes');

        Schema::table('webhook_deliveries', function (Blueprint $table): void {
            $table->dropUnique(['webhook_id', 'idempotency_key']);
            $table->dropColumn(['idempotency_key', 'payload']);
        });

        Schema::table('flows', fn (Blueprint $table) => $table->dropColumn(['run_count', 'failure_count', 'last_error']));
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn([
            'payment_provider', 'provider_customer_id', 'provider_subscription_id', 'cancel_at_period_end', 'cancelled_at',
        ]));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['two_factor_enabled', 'last_two_factor_at']));
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn('portal_limit'));
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
