<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispense_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('dispense_id')
                ->constrained('dispenses')
                ->cascadeOnDelete();

            $table->foreignId('item_id')
                ->constrained('items')
                ->restrictOnDelete();

            $table->unsignedInteger('qty');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispense_items');
    }
};