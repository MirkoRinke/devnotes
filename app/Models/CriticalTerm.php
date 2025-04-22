<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CriticalTerm extends Model {

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'language',
        'severity',
        'created_by_role',
        'created_by_user_id'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'severity' => 'integer',
    ];
}
