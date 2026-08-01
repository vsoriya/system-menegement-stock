<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two indexes for queries that grow with the shop rather than staying small.
 *
 * Neither matters at a few hundred rows. Both matter after a couple of years of
 * trading, and adding them now costs nothing while the tables are still tiny.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            // The movements list orders by created_at with no product filter,
            // and the date range report filters on created_at alone. The
            // existing (product_id, created_at) index cannot serve either,
            // because a composite index is only usable from its first column,
            // so both were falling back to sorting the whole table.
            $table->index('created_at');
        });

        Schema::table('sales', function (Blueprint $table): void {
            // Every money figure in the app asks the same question: completed
            // sales within a date range. Two separate single column indexes
            // meant the database had to pick one and filter the rest by hand.
            $table->index(['status', 'sold_at']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropIndex(['created_at']);
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex(['status', 'sold_at']);
        });
    }
};
