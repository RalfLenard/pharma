<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_transfers', function (Blueprint $table) {
           
            $table->string('remarks')->nullable()->after('prepared_by_position');
        });

        // Separate statement so it fails loudly if duplicates already exist,
        // rather than silently corrupting the unique index.
        Schema::table('print_transfers', function (Blueprint $table) {
            $table->unique('reference_id');
        });
    }

    public function down(): void
    {
        Schema::table('print_transfers', function (Blueprint $table) {
            $table->dropUnique(['reference_id']);
            $table->dropColumn(['date_from', 'date_to', 'remarks']);
        });
    }
};