<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email_domain')->nullable()->index();
            $table->string('registration_ip_hash', 64)->nullable()->index();
            $table->string('device_fingerprint_hash', 64)->nullable()->index();
            $table->timestamp('trial_started_at')->nullable();
            $table->timestamp('blocked_at')->nullable();
        });

        Schema::table('whatsapp_instances', function (Blueprint $table): void {
            $table->uuid('openwa_session_id')->nullable()->unique();
            $table->timestamp('connected_at')->nullable();
            $table->text('last_error')->nullable();
        });

        Schema::table('message_events', function (Blueprint $table): void {
            $table->string('provider_message_id')->nullable()->index();
            $table->string('idempotency_key', 128)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('dispatched_at')->nullable();
            $table->unique(['workspace_id', 'idempotency_key']);
        });

        Schema::table('webhooks', function (Blueprint $table): void {
            $table->unsignedInteger('failure_count')->default(0);
        });

        Schema::create('trial_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email_hash', 64)->unique();
            $table->string('email_domain')->index();
            $table->string('ip_hash', 64)->index();
            $table->string('fingerprint_hash', 64)->nullable()->index();
            $table->string('site_domain')->nullable()->index();
            $table->string('status')->default('accepted')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('trial_identifiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->string('value_hash', 64);
            $table->timestamps();
            $table->unique(['type', 'value_hash']);
        });

        Schema::create('portals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url', 2048);
            $table->string('domain')->unique();
            $table->string('status')->default('pending')->index();
            $table->string('verification_token', 64)->unique();
            $table->timestamp('verified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('inbound_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('whatsapp_instance_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('openwa');
            $table->string('event', 80)->index();
            $table->string('idempotency_key', 191)->unique();
            $table->string('delivery_id', 191)->nullable()->index();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('webhook_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('event', 80)->index();
            $table->string('status')->default('pending')->index();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('api_request_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('api_key_id')->nullable()->constrained('api_keys')->nullOnDelete();
            $table->string('request_id', 64)->unique();
            $table->string('method', 12);
            $table->string('path', 512);
            $table->unsignedSmallInteger('status_code');
            $table->unsignedInteger('duration_ms');
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('inbound_events');
        Schema::dropIfExists('portals');
        Schema::dropIfExists('trial_identifiers');
        Schema::dropIfExists('trial_registrations');

        Schema::table('webhooks', fn (Blueprint $table) => $table->dropColumn('failure_count'));
        Schema::table('message_events', function (Blueprint $table): void {
            $table->dropUnique(['workspace_id', 'idempotency_key']);
            $table->dropColumn(['provider_message_id', 'idempotency_key', 'attempts', 'dispatched_at']);
        });
        Schema::table('whatsapp_instances', function (Blueprint $table): void {
            $table->dropUnique(['openwa_session_id']);
            $table->dropColumn(['openwa_session_id', 'connected_at', 'last_error']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['email_domain', 'registration_ip_hash', 'device_fingerprint_hash', 'trial_started_at', 'blocked_at']);
        });
    }
};
