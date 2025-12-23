<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->after('id'); // إضافة uuid بعد id
            $table->decimal('discount_price', 10, 2)->nullable()->after('price'); // إضافة سعر الخصم
            $table->json('images')->nullable()->after('category_id'); // تغيير الصورة إلى JSON
            $table->boolean('is_active')->default(true)->after('images'); // إضافة حالة التفعيل
            $table->softDeletes(); // دعم الحذف الناعم
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['uuid', 'discount_price', 'images', 'is_active']);
            $table->dropSoftDeletes();
        });
    }
};
