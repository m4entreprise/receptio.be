<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_configs', function (Blueprint $table) {
            $table->boolean('conversation_enabled')->default(false)->after('faq_content');
            $table->text('conversation_prompt')->nullable()->after('conversation_enabled');
            $table->unsignedTinyInteger('max_clarification_turns')->default(2)->after('conversation_prompt');
        });

        Schema::table('calls', function (Blueprint $table) {
            $table->string('channel')->default('menu')->after('ended_at');
            $table->string('conversation_status')->nullable()->after('channel');
            $table->string('resolution_type')->nullable()->after('conversation_status');
            $table->text('conversation_summary')->nullable()->after('resolution_type');
            $table->text('escalation_reason')->nullable()->after('conversation_summary');
        });

        Schema::create('call_turns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained()->cascadeOnDelete();
            $table->string('speaker', 32);
            $table->longText('text');
            $table->decimal('confidence', 5, 4)->nullable();
            $table->unsignedInteger('sequence');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['call_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_turns');

        Schema::table('calls', function (Blueprint $table) {
            $table->dropColumn([
                'channel',
                'conversation_status',
                'resolution_type',
                'conversation_summary',
                'escalation_reason',
            ]);
        });

        Schema::table('agent_configs', function (Blueprint $table) {
            $table->dropColumn([
                'conversation_enabled',
                'conversation_prompt',
                'max_clarification_turns',
            ]);
        });
    }
};
