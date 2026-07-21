<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrintTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_id',
        'transfer_id',
        'printed_by',
        'printed_at',
        'prepared_by',
        'prepared_by_position'
    ];

    protected $casts = [
        'printed_at' => 'datetime',
    ];

    public function transfer()
    {
        return $this->belongsTo(Transfer::class);
    }

    public function printedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'printed_by');
    }
}