<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name');                             // Supply Item name *
            $table->string('vol')->nullable();                  // Volume / Packaging (Pack size)
            $table->string('brand')->nullable();                // Brand
            $table->string('sec')->nullable();                  // Category / Section
            $table->string('lot')->nullable();                  // Lot number
            $table->date('exp')->nullable();                    // Expiration date
            $table->integer('min')->default(0);                 // Min. stock level
            $table->string('fund')->nullable();                 // Fund Source
            $table->integer('order_qty')->default(0);           // On order quantity
            $table->string('unit');                             // Unit * (Required per design)
            $table->string('quarter_delivered')->nullable();
            
            // Stock Tracker Balances / Meta
            $table->integer('init_in')->default(0);             // Initial Stock In (received)
            $table->integer('init_out')->default(0);            // Initial Stock Out (used)
            $table->string('by')->nullable();                   // Performed by / Recorded by
            $table->date('added_date')->nullable();             // Date of entry

            // Operational & Archiving States
            $table->text('notes')->nullable();
            $table->boolean('archived')->default(false);
            $table->date('archived_date')->nullable();
            $table->string('archived_reason')->nullable();
            $table->timestamps();

            // Optimizations
            $table->index(['sec']);
            $table->index(['fund']);
            $table->index(['archived']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};