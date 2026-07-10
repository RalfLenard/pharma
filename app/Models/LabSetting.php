<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabSetting extends Model
{
    protected $fillable = [
        'name', 'address', 'email', 'contact', 'logo_data_url',
    ];

    /**
     * There is only ever one settings row. Fetch it (creating a blank one
     * if it doesn't exist yet) instead of querying by id.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'name' => '',
            'address' => '',
            'email' => '',
            'contact' => '',
            'logo_data_url' => null,
        ]);
    }
}