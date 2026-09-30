<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispenses', function (Blueprint $table) {
            $table->string('reference_no', 30)->nullable()->unique()->after('id'); // e.g. DSP-202609-00012
            $table->timestamp('printed_at')->nullable();                            // last time the slip was printed
            $table->unsignedInteger('print_count')->default(0);                     // how many times it was printed
        });

        // Give every existing record a reference number
        DB::table('dispenses')
            ->whereNull('reference_no')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $ym = Carbon::parse($row->created_at ?? now())->format('Ym');

                    DB::table('dispenses')
                        ->where('id', $row->id)
                        ->update(['reference_no' => sprintf('DSP-%s-%05d', $ym, $row->id)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('dispenses', function (Blueprint $table) {
            $table->dropUnique(['reference_no']);
            $table->dropColumn(['reference_no', 'printed_at', 'print_count']);
        });
    }
};