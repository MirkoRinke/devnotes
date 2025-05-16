<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserFollower extends Model {

    /**
     *  The traits used in the model
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

        // Basic
        'user_id',
        'follower_id',

        // Update info
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        // Relationships
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
     * 
     * @example | $userFollower->user // Access the related user
     * @example | UserFollower::with('user')->get() // Eager loading
     */
    public function user() {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the follower that owns the UserFollower.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * 
     * @example | $userFollower->follower // Access the related follower
     * @example | UserFollower::with('follower')->get() // Eager loading
     */
    public function follower() {
        return $this->belongsTo(User::class, 'follower_id');
    }
}
