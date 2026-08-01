<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 60)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('unit', 20)->default('pcs');
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('reorder_level')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index('name');
            $table->index(['quantity', 'reorder_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
