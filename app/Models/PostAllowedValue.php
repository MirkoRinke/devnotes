<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostAllowedValue extends Model {

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
        // 'id',                    || Laravel will automatically handle the 'id' field
        // 'created_at',            || Laravel will automatically handle the 'created_at' field

        // Basic
        'name',
        'type',
        // 'created_by_role',       || Explicitly set in controller from authenticated user's role
        // 'created_by_user_id',    || Explicitly set in controller from authenticated user's id

        // Counts
        // 'post_count',            || Explicitly set in PostController when creating/updating/deleting posts

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
        'pivot'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * Get the user who created this post allowed value
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * 
     * @example | $postAllowedValue->createdByUser // Access the related user
     * @example | PostAllowedValue::with('createdByUser')->get() // Eager loading
     */
    public function createdByUser() {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get the user who created this post allowed value
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * 
     * @example | $postAllowedValue->user // Access the user of the post allowed value
     * @example | PostAllowedValue::with('user')->get() // Eager loading
     */
    public function user() {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
