<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('deliveries', 'proof_photo_path')) {
            Schema::table('deliveries', function (Blueprint $table) {
                $table->dropColumn('proof_photo_path');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('deliveries', 'proof_photo_path')) {
            Schema::table('deliveries', function (Blueprint $table) {
                $table->string('proof_photo_path')->nullable();
            });
        }
    }
};
