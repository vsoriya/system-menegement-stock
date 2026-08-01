<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->index();

            // Signed delta applied to the product quantity. Negative for stock out.
            $table->integer('quantity_change');

            // Audit trail so history stays readable even after later movements.
            $table->unsignedInteger('quantity_before');
            $table->unsignedInteger('quantity_after');

            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
