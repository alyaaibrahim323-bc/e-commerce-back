<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('orders', function (Blueprint $table) {
            // تعديل حالة الطلب لتكون ENUM فقط إذا كانت ليست ENUM بالفعل
            if (!Schema::hasColumn('orders', 'status')) {
                $table->enum('status', ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])
                      ->default('pending')
                      ->change();
            }

            if (!Schema::hasColumn('orders', 'address_id')) {
                $table->foreignId('address_id')
                      ->nullable()
                      ->constrained()
                      ->onDelete('cascade');
            }

            if (!Schema::hasColumn('orders', 'payment_id')) {
                $table->string('payment_id')->nullable();
            }
        });
    }

    public function down() {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'status')) {
                $table->string('status')->default('pending')->change();
            }

            if (Schema::hasColumn('orders', 'address_id')) {
                $table->dropForeign(['address_id']);
                $table->dropColumn('address_id');
            }

            if (Schema::hasColumn('orders', 'payment_id')) {
                $table->dropColumn('payment_id');
            }
        });
    }
};
