<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('payment_method')->default('cod');
            $table->string('payment_status')->default('unrecorded');
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('paymongo_checkout_session_id')->nullable()->unique();
            $table->text('paymongo_checkout_url')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamp('paid_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropUnique(['paymongo_checkout_session_id']);
            $table->dropColumn([
                'payment_method',
                'payment_status',
                'amount',
                'paymongo_checkout_session_id',
                'paymongo_checkout_url',
                'scanned_at',
                'paid_at',
            ]);
        });
    }
};
