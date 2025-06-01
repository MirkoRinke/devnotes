<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLike extends Model {

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
        // 'user_id',               || Explicitly set in controller from authenticated user
        // 'likeable_type',         || Explicitly set in controller after $typeMap conversion
        // 'likeable_id',           || Explicitly set in controller from validated input
        // 'type',                  || Explicitly set in controller from simple type value

        // Update info
        // 'updated_at',            || Laravel will automatically handle the 'updated_at' field
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        // Relationships
        'user',
        'likeable',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * Get the user that owns the like
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * 
     * @example | $userLike->user // Access the related user
     * @example | UserLike::with('user')->get() // Eager loading
     */
    public function user() {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the owning likeable model
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     * 
     * @example | $userLike->likeable // Access the related likeable model
     * @example | UserLike::with('likeable')->get() // Eager loading
     */
    public function likeable() {
        return $this->morphTo();
    }
}
