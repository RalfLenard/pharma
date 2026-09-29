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
    ];

    public function dispenseItems(): HasMany
    {
        return $this->hasMany(DispenseItem::class);
    }
}