<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dispense extends Model
{
    protected $fillable = [
        'full_name',
        'brgy',
        'date_of_birth',
        'sex',
        'has_philhealth',
        'philhealth_number',
        'philhealth_facility',
        'qty',
        'dispense_by',
        'received_by',
        'receiver_relationship',
    ];

    protected $casts = [
        'date_of_birth'  => 'date:Y-m-d',
        'has_philhealth' => 'boolean',
        'qty'            => 'integer',
        'printed_at'     => 'datetime',
        'print_count'    => 'integer',
    ];

    protected static function booted(): void
    {
        // Reference number: DSP-{YYYYMM}-{id padded to 5}  →  DSP-202609-00012
        // Built from the id, so it is always unique (no race conditions).
        static::created(function (Dispense $dispense) {
            $dispense->reference_no = sprintf('DSP-%s-%05d', $dispense->created_at->format('Ym'), $dispense->id);
            $dispense->saveQuietly();
        });
    }

    public function dispenseItems(): HasMany
    {
        return $this->hasMany(DispenseItem::class);
    }
}