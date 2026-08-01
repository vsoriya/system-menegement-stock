<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);

            // Phone matters more than email here, and is what a shop searches
            // by, so it is indexed. Not unique: families share numbers.
            $table->string('phone', 40)->nullable()->index();

            $table->string('email', 150)->nullable();
            $table->string('address', 255)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            // Soft deleted so past invoices keep pointing at a real customer.
            $table->softDeletes()->index();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
