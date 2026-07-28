<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintTransfer extends Model
{
    protected $fillable = [
        'reference_id',
        'transfer_id',
        'printed_by',
        'prepared_by',
        'prepared_by_position',
        'date_from',
        'date_to',
        'remarks',
        'printed_at',
    ];

    protected $casts = [
        'date_from'  => 'date',
        'date_to'    => 'date',
        'printed_at' => 'datetime',
    ];

    public function printedBy()
    {
        return $this->belongsTo(User::class, 'printed_by');
    }

    public function transfer()
    {
        return $this->belongsTo(Transfer::class);
    }
}