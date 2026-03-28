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
        Schema::table('call_messages', function (Blueprint $table) {
            $table->string('status')->default('new')->after('call_id');
            $table->foreignId('assigned_to_user_id')->nullable()->after('notified_at')->constrained('users')->nullOnDelete();
            $table->foreignId('handled_by_user_id')->nullable()->after('assigned_to_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable()->after('handled_by_user_id');

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'caller_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('call_messages', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropIndex(['tenant_id', 'created_at']);
            $table->dropIndex(['tenant_id', 'caller_number']);

            $table->dropConstrainedForeignId('handled_by_user_id');
            $table->dropConstrainedForeignId('assigned_to_user_id');
            $table->dropColumn(['status', 'handled_at']);
        });
    }
};
