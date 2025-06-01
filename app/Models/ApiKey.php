<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model {

    /**
     * The traits used in the model
     */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        // Default
        // 'id',                            || Laravel will automatically handle the 'id' field
        // 'created_at',                    || Laravel will automatically handle the 'created_at' field

        // Basic
        'name',
        // 'key',                           || Explicitly set in controller using Str::random(40)
        // 'active',                        || Explicitly set in controller (default true)

        // Update info
        // 'updated_at',                    || Laravel will automatically handle the 'updated_at' field
        // 'last_used_at'                   || Updated by ValidateApiKey middleware on each API request
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        // Basic
        'key'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        // Basic
        'active' => 'boolean',

        // Update info
        'last_used_at' => 'datetime',
    ];
}
