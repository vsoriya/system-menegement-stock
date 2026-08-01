<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_takes', function (Blueprint $table) {
            $table->id();
            $table->string('reference');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            // open -> posted, or cancelled while still open.
            $table->string('status', 20)->default('open')->index();

            $table->date('counted_at');
            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_take_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_take_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Snapshot of the system balance when the sheet was generated, so a
            // later movement cannot silently change what the count compared to.
            $table->unsignedInteger('expected_quantity');

            // Null until someone actually counts this line.
            $table->unsignedInteger('counted_quantity')->nullable();

            $table->timestamps();

            $table->unique(['stock_take_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_take_lines');
        Schema::dropIfExists('stock_takes');
    }
};
