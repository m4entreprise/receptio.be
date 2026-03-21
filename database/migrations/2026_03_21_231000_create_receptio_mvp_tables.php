<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('locale')->default('fr-BE');
            $table->string('timezone')->default('Europe/Brussels');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::create('phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('twilio');
            $table->string('label')->nullable();
            $table->string('phone_number')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('agent_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('agent_name')->default('Receptio');
            $table->text('welcome_message')->nullable();
            $table->text('after_hours_message')->nullable();
            $table->text('faq_content')->nullable();
            $table->string('transfer_phone_number')->nullable();
            $table->string('notification_email')->nullable();
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->json('business_days')->nullable();
            $table->timestamps();
        });

        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('phone_number_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_sid')->nullable()->unique();
            $table->string('direction')->default('inbound');
            $table->string('status')->default('received');
            $table->string('from_number')->nullable();
            $table->string('to_number')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->longText('transcript')->nullable();
            $table->text('summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('call_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('call_id')->constrained()->cascadeOnDelete();
            $table->string('caller_name')->nullable();
            $table->string('caller_number')->nullable();
            $table->text('message_text')->nullable();
            $table->text('recording_url')->nullable();
            $table->unsignedInteger('recording_duration')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_messages');
        Schema::dropIfExists('calls');
        Schema::dropIfExists('agent_configs');
        Schema::dropIfExists('phone_numbers');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::dropIfExists('tenants');
    }
};
