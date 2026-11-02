<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->string('destination');
            $table->string('driver')->nullable();
            $table->string('vehicle')->nullable();
            $table->timestamp('scheduled_at');
            $table->enum('status', ['in_transit', 'delivered'])->default('in_transit');
            $table->string('qr_token', 64)->unique();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};