<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('currency', 3)->default('PHP');
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->unique()->constrained()->restrictOnDelete();
            $table->string('payment_method');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->string('status');
            $table->string('payment_reference')->nullable();
            $table->timestamp('collected_at');
            $table->timestamp('remitted_at')->nullable();
            $table->foreignId('remitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');

        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
