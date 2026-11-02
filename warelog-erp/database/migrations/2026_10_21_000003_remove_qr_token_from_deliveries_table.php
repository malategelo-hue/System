<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropUnique(['qr_token']);
            $table->dropColumn('qr_token');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('qr_token', 64)->nullable()->unique();
        });

        DB::table('deliveries')->select('id')->orderBy('id')->chunkById(100, function ($deliveries) {
            foreach ($deliveries as $delivery) {
                DB::table('deliveries')
                    ->where('id', $delivery->id)
                    ->update(['qr_token' => bin2hex(random_bytes(24))]);
            }
        });
    }
};
