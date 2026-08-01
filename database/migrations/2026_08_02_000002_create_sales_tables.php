<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();

            // Walk in customers are the norm, so this stays optional.
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // completed, or voided when a sale is reversed.
            $table->string('status', 20)->default('completed')->index();

            $table->string('payment_method', 20)->default('cash');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            // What the customer handed over, and what went back to them. Kept
            // rather than recomputed so a reprinted receipt matches the first.
            $table->decimal('paid', 12, 2)->default(0);
            $table->decimal('change_due', 12, 2)->default(0);

            $table->timestamp('sold_at')->index();
            $table->timestamp('voided_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();

            // Restricted, because deleting a product must never quietly rewrite
            // what an invoice said was sold.
            $table->foreignId('product_id')->constrained()->restrictOnDelete();

            $table->unsignedInteger('quantity');

            // Both prices are snapshots taken at the moment of sale. Reading
            // them off the product later would rewrite history every time a
            // price changed, and would make profit reporting meaningless.
            $table->decimal('unit_price', 12, 2);
            $table->decimal('unit_cost', 12, 2)->default(0);

            $table->timestamps();

            // Scanning the same item twice adds to the existing line instead of
            // creating a second one, which also keeps the receipt readable.
            $table->unique(['sale_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_lines');
        Schema::dropIfExists('sales');
    }
};
