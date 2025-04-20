<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForbiddenName extends Model {

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'match_type',
        'created_by_role',
        'created_by_user_id'
    ];

    /**
     * Get the user who created this forbidden name
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function creator() {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
