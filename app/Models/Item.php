<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name', 
        'vol', 
        'brand', 
        'sec', 
        'lot', 
        'exp', 
        'min', 
        'fund',
        'order_qty', 
        'unit', 
        'init_in',    
        'init_out',   
        'by',         
        'added_date',
        'quarter_delivered',   // ← Added
        'notes', 
        'archived', 
        'archived_date', 
        'archived_reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'exp' => 'date:Y-m-d',
        'added_date' => 'date:Y-m-d',
        'archived_date' => 'date:Y-m-d',
        'archived' => 'boolean',
        'min' => 'integer',
        'order_qty' => 'integer',
        'init_in' => 'integer',
        'init_out' => 'integer',
        // No cast needed for quarter_delivered (it's a string like "Q3 2026")
    ];

    /**
     * Get the transactions for the item.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the wastage records for the item.
     */
    public function wastageRecords(): HasMany
    {
        return $this->hasMany(WastageRecord::class);
    }

    // Optional: Helper method for better readability
    public function getQuarterDeliveredAttribute($value)
    {
        return $value; // You can add formatting logic here if needed
    }
}