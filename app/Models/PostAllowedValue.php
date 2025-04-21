<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostAllowedValue extends Model {

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'type',
        'created_by_role',
        'created_by_user_id',
    ];


    /**
     * Get the user who created this post allowed value
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createdByUser() {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
