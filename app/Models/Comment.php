<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model {

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
        'user',
        'parent',
        'children'
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
     */
    public function post() {
        return $this->belongsTo(Post::class);
    }

    /**
     * Get the user that owns the comment
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user() {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent comment
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent() {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /**
     * Get the children comments
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children() {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    /**
     * Get all likes for this comment
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function likes() {
        return $this->morphMany(UserLike::class, 'likeable');
    }
}
