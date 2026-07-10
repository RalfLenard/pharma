<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['in', 'out', 'adj']);   // in = stock in, out = stock out, adj = adjustment
            $table->integer('qty');
            $table->date('date');
            $table->string('performed_by')->nullable();
            $table->text('note')->nullable();
             $table->string('by')->nullable();    
            $table->timestamps();

            $table->index(['item_id', 'date']);
            $table->index('type');
            $table->index('date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('transactions');
    }
};