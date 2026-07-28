<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_transfers', function (Blueprint $table) {
            $table->date('date_from')->nullable()->after('reference_id');
            $table->date('date_to')->nullable()->after('date_from');
        });
    }

    public function down(): void
    {
        Schema::table('print_transfers', function (Blueprint $table) {
            $table->dropColumn(['date_from', 'date_to']);
        });
    }
};