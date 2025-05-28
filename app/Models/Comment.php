<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model {

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
        'content',
        'post_id',
        'parent_id',
        // 'user_id',               || Automatically created in the controller
        // 'parent_content',        || Automatically created in the controller
        // 'is_deleted',            || Automatically created in the controller
        // 'depth',                 || Automatically created in the controller   

        // Counts
        // 'likes_count',           || Automatically handled by the UserLike model
        // 'reports_count',         || Automatically handled by the UserReport model  

        // Update info
        // 'updated_at',            || Laravel will automatically handle the 'updated_at' field
        // 'is_updated',            || Automatically created in the controller
        // 'updated_by_role',       || Automatically created in the controller

        // Moderation info
        // 'moderation_info',       || Automatically created in the controller
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        // Relationships
        'user',
        'parent',
        'children',

        // Counts
        'reports_count',

        // Moderation info
        'moderation_info',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        // Basic
        'is_deleted' => 'boolean',
        'depth' => 'integer',

        // Counts
        'likes_count' => 'integer',
        'reports_count' => 'integer',

        // Update info
        'is_updated' => 'boolean',
        'updated_by_role' => 'string',

        // Moderation info
        'moderation_info' => 'array',
    ];

    /**
     * Get the post that owns the comment
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * 
     * @example | $comment->post // Access the related post
     * @example | Comment::with('post')->get() // Eager loading
     */
    public function post() {
        return $this->belongsTo(Post::class);
    }

    /**
     * Get the user that owns the comment
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * 
     * @example | $comment->user // Access the related user
     * @example | Comment::with('user')->get() // Eager loading
     */
    public function user() {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent comment
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * 
     * @example | $comment->parent // Access the parent comment
     * @example | Comment::with('parent')->get() // Eager loading
     */
    public function parent() {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /**
     * Get the children comments
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     * 
     * @example | $comment->children // Access the children comments
     * @example | Comment::with('children')->get() // Eager loading
     */
    public function children() {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    /**
     * Get all likes for this comment
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     * 
     * @example | $comment->likes // Access the likes
     * @example | Comment::with('likes')->get() // Eager loading
     */
    public function likes() {
        return $this->morphMany(UserLike::class, 'likeable');
    }
}
