<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            // draft -> approved -> received, or cancelled from draft/approved.
            $table->string('status', 20)->default('draft')->index();

            $table->date('ordered_at');
            $table->date('expected_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity_ordered');

            // Grows as deliveries arrive, allowing partial receipts.
            $table->unsignedInteger('quantity_received')->default(0);

            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->timestamps();

            // A product should only appear once per order.
            $table->unique(['purchase_order_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
    }
};
