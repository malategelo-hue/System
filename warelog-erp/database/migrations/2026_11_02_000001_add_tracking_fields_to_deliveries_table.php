<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_to')->nullable()->index();
            $table->timestamp('trip_started_at')->nullable();

            // Last known driver position (realtime tracking)
            $table->decimal('last_lat', 10, 7)->nullable();
            $table->decimal('last_lng', 10, 7)->nullable();
            $table->float('last_accuracy')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            // Proof of delivery
            $table->string('scanned_code')->nullable();
            $table->decimal('proof_lat', 10, 7)->nullable();
            $table->decimal('proof_lng', 10, 7)->nullable();
            $table->float('proof_accuracy')->nullable();
            $table->timestamp('proof_taken_at')->nullable();
        });

        // An older migration added and then dropped this column; add it back for the new proof flow.
        if (! Schema::hasColumn('deliveries', 'proof_photo_path')) {
            Schema::table('deliveries', function (Blueprint $table) {
                $table->string('proof_photo_path')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropIndex(['assigned_to']);
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn([
                'assigned_to', 'trip_started_at',
                'last_lat', 'last_lng', 'last_accuracy', 'last_seen_at',
                'scanned_code', 'proof_lat', 'proof_lng', 'proof_accuracy', 'proof_taken_at',
                'proof_photo_path',
            ]);
        });
    }
};
