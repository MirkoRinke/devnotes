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
        // 'id',                    || Laravel will automatically handle the 'id' field
        // 'created_at',            || Laravel will automatically handle the 'created_at' field

        // Basic
        // 'user_id',               || Explicitly set in controller from user being followed
        // 'follower_id',           || Explicitly set in controller from authenticated user

        // Update info
        // 'last_posts_visited_at'  || Can be set when user visits posts of the followed user
        // 'updated_at',            || Laravel will automatically handle the 'updated_at' field
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
    protected $casts = [
        'last_posts_visited_at' => 'datetime',
    ];

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
