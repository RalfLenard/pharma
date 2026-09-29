<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispenses', function (Blueprint $table) {
            $table->id();

            // Patient Information
            $table->string('full_name');
            $table->string('brgy')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('sex', ['Male', 'Female'])->nullable();

            // PhilHealth Information
            $table->boolean('has_philhealth')->default(false);
            $table->string('philhealth_facility')->nullable();
            $table->string('philhealth_number', 50)->nullable();

            // Dispensing Information
            $table->unsignedInteger('qty')->default(1);
            $table->string('dispense_by');
            $table->string('received_by');
            $table->string('receiver_relationship')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispenses');
    }
};