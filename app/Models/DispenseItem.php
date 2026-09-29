<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispenseItem extends Model
{
    protected $fillable = ['dispense_id', 'item_id', 'qty'];

    protected $casts = ['qty' => 'integer'];

    public function dispense(): BelongsTo
    {
        return $this->belongsTo(Dispense::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}