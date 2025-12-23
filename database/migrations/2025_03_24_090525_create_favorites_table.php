<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();

            // للمستخدمين المسجلين (يمكن أن تكون NULL)
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained()
                  ->onDelete('cascade');

            // للزوار (UUID - يمكن أن تكون NULL)
            $table->uuid('guest_uuid')->nullable();

            // العلاقة مع المنتجات
            $table->foreignId('product_id')
                  ->constrained()
                  ->onDelete('cascade');

            // Constraints لمنع التكرار
            $table->unique(['user_id', 'product_id']);
            $table->unique(['guest_uuid', 'product_id']);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('favorites');
    }
};
