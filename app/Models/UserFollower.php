<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserFollower extends Model {

    /**
     *  The traits used in the controller
     */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        // Default
        'id',
        'created_at',
        'updated_at',

        // Basic
        'user_id',
        'follower_id',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'follower',
        'user',
    ];


    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [];


    /**
     * Get the user that owns the UserFollower.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user() {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the follower that owns the UserFollower.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function follower() {
        return $this->belongsTo(User::class, 'follower_id');
    }
}
