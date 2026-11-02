<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id')->nullable()->index();
            $table->unsignedBigInteger('delivery_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('type', 40);
            $table->string('message');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_events');
    }
};
