<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id', 'is_new_item', 'item_name', 'qty', 'lot_number',
        'expiry', 'unit', 'section', 'note', 'order_date', 'received'
    ];

    protected $casts = [
        'expiry' => 'date',
        'order_date' => 'date',
        'is_new_item' => 'boolean',
        'received' => 'boolean',
    ];

    // Relationships
    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}