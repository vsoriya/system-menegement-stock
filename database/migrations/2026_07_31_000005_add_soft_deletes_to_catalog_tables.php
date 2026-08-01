<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalog records are soft deleted so a mistaken delete never destroys the
     * stock movement history attached to a product.
     */
    public function up(): void
    {
        foreach (['products', 'categories', 'suppliers'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->softDeletes()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['products', 'categories', 'suppliers'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }
    }
};
