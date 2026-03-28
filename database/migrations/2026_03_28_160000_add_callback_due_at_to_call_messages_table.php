<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_messages', function (Blueprint $table) {
            $table->timestamp('callback_due_at')->nullable()->after('handled_at');
            $table->index(['tenant_id', 'callback_due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('call_messages', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'callback_due_at']);
            $table->dropColumn('callback_due_at');
        });
    }
};
