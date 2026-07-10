<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WastageRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id', 'transaction_id', 'item_name', 'item_unit', 'item_lot', 'item_sec',
        'type', 'qty', 'date', 'by', 'reason',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'qty' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}