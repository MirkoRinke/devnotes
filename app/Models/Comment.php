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
        'id',
        'created_at',

        // Basic
        'post_id',
        'user_id',
        'parent_id',
        'content',
        'parent_content',
        'is_deleted',
        'depth',

        // Counts
        'likes_count',
        'reports_count',

        // Update info
        'updated_at',
        'is_updated',
        'updated_by_role',

        // Moderation info
        'moderation_info',
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
