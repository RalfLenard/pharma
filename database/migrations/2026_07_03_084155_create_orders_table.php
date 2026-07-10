<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->nullable()->constrained()->onDelete('set null');
            $table->boolean('is_new_item')->default(false);
            $table->string('item_name')->nullable();
            $table->integer('qty');
            $table->string('lot_number')->nullable();
            $table->date('expiry')->nullable();
            $table->string('unit')->nullable();
            $table->string('section')->nullable();
            $table->text('note')->nullable();
            $table->date('order_date');
            $table->boolean('received')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('orders');
    }
};