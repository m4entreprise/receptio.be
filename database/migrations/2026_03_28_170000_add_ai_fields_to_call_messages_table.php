<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_messages', function (Blueprint $table) {
            $table->string('transcription_status')->default('pending')->after('callback_due_at');
            $table->string('transcript_provider')->nullable()->after('transcription_status');
            $table->text('transcription_error')->nullable()->after('transcript_provider');
            $table->timestamp('transcription_processed_at')->nullable()->after('transcription_error');
            $table->text('ai_summary')->nullable()->after('transcription_processed_at');
            $table->string('ai_intent')->nullable()->after('ai_summary');
            $table->string('urgency_level')->nullable()->after('ai_intent');
            $table->timestamp('automation_processed_at')->nullable()->after('urgency_level');

            $table->index(['tenant_id', 'transcription_status']);
            $table->index(['tenant_id', 'urgency_level']);
        });
    }

    public function down(): void
    {
        Schema::table('call_messages', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'transcription_status']);
            $table->dropIndex(['tenant_id', 'urgency_level']);
            $table->dropColumn([
                'transcription_status',
                'transcript_provider',
                'transcription_error',
                'transcription_processed_at',
                'ai_summary',
                'ai_intent',
                'urgency_level',
                'automation_processed_at',
            ]);
        });
    }
};
