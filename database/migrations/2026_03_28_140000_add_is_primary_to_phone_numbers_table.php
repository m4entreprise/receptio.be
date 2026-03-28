<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('phone_numbers', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('is_active');
            $table->index(['tenant_id', 'is_primary']);
        });

        DB::table('phone_numbers')
            ->orderBy('tenant_id')
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->get()
            ->groupBy('tenant_id')
            ->each(function ($numbers) {
                $primaryId = $numbers->first()?->id;

                if ($primaryId) {
                    DB::table('phone_numbers')
                        ->where('id', $primaryId)
                        ->update(['is_primary' => true]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('phone_numbers', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'is_primary']);
            $table->dropColumn('is_primary');
        });
    }
};
