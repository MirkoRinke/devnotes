<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\User;
use App\Models\UserFavorite;


class Post extends Model {

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
        'user_id',
        'title',
        'code',
        'description',
        'resources',
        'language',
        'images',
        'videos',
        'external_source_previews',
        'category',
        'post_type',
        'technology',
        'tags',
        'status',

        // Counts
        'favorite_count',
        'likes_count',
        'reports_count',
        'comments_count',

        // Update info
        'updated_at',
        'is_updated',
        'updated_by_role',
        'last_comment_at',

        // History
        'history',

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
        'moderation_info',
        'reports_count'
    ];


    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        // Basic
        'resources' => 'array',
        'language' => 'array',
        'technology' => 'array',
        'images' => 'array',
        'videos' => 'array',
        'external_source_previews' => 'array',
        'tags' => 'array',

        // Counts
        'favorite_count' => 'integer',
        'likes_count' => 'integer',
        'reports_count' => 'integer',
        'comments_count' => 'integer',

        // Update info
        'is_updated' => 'boolean',
        'updated_by_role' => 'string',
        'last_comment_at' => 'datetime',

        // History
        'history' => 'array',

        // Moderation info
        'moderation_info' => 'array',
    ];


    /**
     * Get the user that owns the post.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user() {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the favorites for the post.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function favorites() {
        return $this->hasMany(UserFavorite::class);
    }

    /**
     * Get all reports for this post
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function reports() {
        return $this->morphMany(UserReport::class, 'reportable');
    }

    /**
     * Get all comments for this post
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function comments() {
        return $this->hasMany(Comment::class);
    }

    /**
     * Get all likes for this post
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function likes() {
        return $this->morphMany(UserLike::class, 'likeable');
    }
}
