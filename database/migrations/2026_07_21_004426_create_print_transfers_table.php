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
        Schema::create('print_transfers', function (Blueprint $table) {
            $table->id();

            // Report Reference Number (duplicates allowed if desired)
            $table->string('reference_id');

            // Optional transfer reference
            $table->foreignId('transfer_id')
                ->nullable()
                ->constrained('transfers')
                ->nullOnDelete();

            // Logged-in user who printed (optional)
            $table->foreignId('printed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('prepared_by_position')->nullable();

            // Name entered in the print dialog
            $table->string('prepared_by')->nullable();

            // Date & time printed
            $table->timestamp('printed_at')->useCurrent();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_transfers');
    }
};