<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            // Relative path on the public disk, null when no image uploaded.
            $table->string('image_path')->nullable()->after('description');

            // Nullable and unique: many products legitimately have no barcode,
            // and MySQL allows repeated NULLs in a unique index.
            $table->string('barcode', 64)->nullable()->unique()->after('sku');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique(['barcode']);
            $table->dropColumn(['image_path', 'barcode']);
        });
    }
};
