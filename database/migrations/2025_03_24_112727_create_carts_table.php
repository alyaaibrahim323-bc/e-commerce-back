<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{// database/migrations/xxxx_create_carts_table.php

public function up()
{
    Schema::create('carts', function (Blueprint $table) {
        $table->id();

        // للمستخدمين المسجلين
        $table->foreignId('user_id')
              ->nullable()
              ->constrained()
              ->onDelete('cascade');

        // للزوار
        $table->uuid('guest_uuid')->nullable();

        // المنتج والكمية
        $table->foreignId('product_id')
              ->constrained()
              ->onDelete('cascade');
        $table->integer('quantity')->default(1);

        // Constraints لمنع التكرار
        $table->unique(['user_id', 'product_id']);
        $table->unique(['guest_uuid', 'product_id']);

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
