<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\User;
use App\Models\UserFavorite;
use App\Models\PostAllowedValue;

class Post extends Model {

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
        // 'id',                        || Laravel will automatically handle the 'id' field
        // 'created_at',                || Laravel will automatically handle the 'created_at' field

        // Basic
        // 'user_id',                   || Explicitly set in controller from authenticated user
        'title',
        'code',
        'description',
        'images',
        'videos',
        'resources',
        'category',
        'post_type',
        'status',
        // 'external_source_previews',  || Generated in controller by ExternalSourceService

        // Counts
        // 'favorite_count',            || Automatically handled by the UserFavorite model
        // 'likes_count',               || Automatically handled by the UserLike model
        // 'reports_count',             || Automatically handled by the UserReport model
        // 'comments_count',            || Automatically handled by the Comment model

        // Update info
        // 'updated_at',                || Laravel will automatically handle the 'updated_at' field
        // 'is_updated',                || Explicitly set in controller during update operations
        // 'updated_by_role',           || Explicitly set in controller based on updating user's role
        // 'last_comment_at',           || Updated by CommentController when comments are added/modified

        // History
        // 'history',                   || Generated in controller by HistoryService

        // Moderation info
        // 'moderation_info',           || Set by ModerationService during admin/moderator actions
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        // Relationships
        'user',

        // Counts
        'reports_count',

        // Moderation info
        'moderation_info',
    ];


    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        // Basic
        'resources' => 'array',
        'images' => 'array',
        'videos' => 'array',
        'external_source_previews' => 'array',

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
     * 
     * @example | $post->user // Access the related user
     * @example | Post::with('user')->get() // Eager loading
     */
    public function user() {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the favorites for the post.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     * 
     * @example | $post->favorites // Access the related favorites
     * @example | Post::with('favorites')->get() // Eager loading
     */
    public function favorites() {
        return $this->hasMany(UserFavorite::class);
    }

    /**
     * Get all reports for this post
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     * 
     * @example | $post->reports // Access the related reports
     * @example | Post::with('reports')->get() // Eager loading
     */
    public function reports() {
        return $this->morphMany(UserReport::class, 'reportable');
    }

    /**
     * Get all comments for this post
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     * 
     * @example | $post->comments // Access the related comments
     * @example | Post::with('comments')->get() // Eager loading
     */
    public function comments() {
        return $this->hasMany(Comment::class);
    }

    /**
     * Get all likes for this post
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     * 
     * @example | $post->likes // Access the related likes
     * @example | Post::with('likes')->get() // Eager loading
     */
    public function likes() {
        return $this->morphMany(UserLike::class, 'likeable');
    }

    /**
     * Get all tags for this post
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     * 
     * @example | $post->tags // Access the related tags
     * @example | Post::with('tags')->get() // Eager loading
     */
    public function tags() {
        return $this->belongsToMany(
            PostAllowedValue::class,
            'post_tags',
            'post_id',
            'post_allowed_value_id'
        )->where('type', 'tag');
    }

    /**
     * Get all languages for this post
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     * 
     * @example | $post->languages // Access the related languages
     * @example | Post::with('languages')->get() // Eager loading
     */
    public function languages() {
        return $this->belongsToMany(
            PostAllowedValue::class,
            'post_languages',
            'post_id',
            'post_allowed_value_id'
        )->where('type', 'language');
    }

    /**
     * Get all technologies for this post
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     * 
     * @example | $post->technologies // Access the related technologies
     * @example | Post::with('technologies')->get() // Eager loading
     */
    public function technologies() {
        return $this->belongsToMany(
            PostAllowedValue::class,
            'post_technologies',
            'post_id',
            'post_allowed_value_id'
        )->where('type', 'technology');
    }
}
