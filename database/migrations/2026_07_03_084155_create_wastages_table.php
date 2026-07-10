<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wastage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            // Denormalized snapshot of the item at time of wastage
            $table->string('item_name')->nullable();
            $table->string('item_unit')->nullable();
            $table->string('item_lot')->nullable();
            $table->string('item_sec')->nullable();
            $table->enum('type', ['expired', 'spoiled', 'broken', 'other']);
            $table->integer('qty');
            $table->date('date');
            $table->string('by')->nullable();
            $table->text('reason');
            $table->timestamps();

            $table->index(['item_id', 'date']);
            $table->index(['type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wastage_records');
    }
};