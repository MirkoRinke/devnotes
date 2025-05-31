<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserFavorite extends Model {

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
        // 'id',                || Laravel will automatically handle the 'id' field
        // 'created_at',        || Laravel will automatically handle the 'created_at' field

        // Basic
        // 'user_id',           || Automatically created in the controller
        // 'post_id',           || Automatically created in the controller

        // Update info
        // 'updated_at',        || Laravel will automatically handle the 'updated_at' field
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * Get the user that owns the UserFavorite
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * 
     * @example | $userFavorite->user // Access the related user
     * @example | UserFavorite::with('user')->get() // Eager loading
     */
    public function user() {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the post that owns the UserFavorite
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * 
     * @example | $userFavorite->post // Access the related post
     * @example | UserFavorite::with('post')->get() // Eager loading
     */
    public function post() {
        return $this->belongsTo(Post::class);
    }
}
