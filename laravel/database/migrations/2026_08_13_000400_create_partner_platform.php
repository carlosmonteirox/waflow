<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_platform_admin')->default(false)->index();
        });

        Schema::create('partners', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status', 30)->default('active')->index();
            $table->string('brand_name')->nullable();
            $table->string('primary_color', 20)->default('#3b82f6');
            $table->string('custom_domain')->nullable()->unique();
            $table->decimal('commission_percent', 5, 2)->default(20);
            $table->string('referral_code', 6)->unique();
            $table->timestamps();
        });

        Schema::create('partner_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30)->default('sales');
            $table->string('status', 30)->default('active')->index();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['partner_id', 'user_id']);
        });

        Schema::create('partner_workspaces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('active')->index();
            $table->decimal('commission_percent', 5, 2)->nullable();
            $table->timestamp('referred_at')->nullable();
            $table->timestamps();
            $table->index(['partner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_workspaces');
        Schema::dropIfExists('partner_members');
        Schema::dropIfExists('partners');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_platform_admin'));
    }
};
