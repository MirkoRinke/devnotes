<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Laravel\Sanctum\HasApiTokens;

use App\Models\Post;
use App\Models\UserFavorite;

class User extends Authenticatable implements MustVerifyEmail {

    /**
     * The traits used in the model
     */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        // Default
        // 'id',                            || Laravel will automatically handle the 'id' field
        'name',
        'email',
        // 'email_verified_at',             || Set by email verification process
        'password',
        // 'created_at',                    || Laravel will automatically handle the 'created_at' field

        // Basic
        'display_name',
        // 'role',                          || Set during account creation (default is 'user')

        // Ban info
        // 'is_banned',                     || Explicitly set in UserController by admins
        // 'was_ever_banned',               || Explicitly set in UserController during ban actions

        // Update info
        // 'updated_at',                    || Laravel will automatically handle the 'updated_at' field
        // 'last_post_created_at',          || Set Automatically when a post is created

        // Moderation info
        // 'moderation_info',               || Set by ModerationService during moderation actions

        // Account info
        // 'account_purpose',               || Set during account creation (default is 'regular')
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        // Relationships
        'profile',

        // Default
        'password',
        'remember_token',

        // Ban info
        'is_banned',
        'was_ever_banned',

        // Moderation info
        'moderation_info',

        // Account info
        'account_purpose',
    ];


    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            // Default
            'email_verified_at' => 'datetime',
            'password' => 'hashed',

            // Ban info
            'is_banned' => 'datetime',
            'was_ever_banned' => 'boolean',

            // Update info
            'last_post_created_at' => 'datetime',

            // Moderation info
            'moderation_info' => 'json',

            // Account info
            'account_purpose' => 'string',
        ];
    }

    /**
     * Get the posts for the user.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     * 
     * @example | $user->posts // Access the related posts
     * @example | User::with('posts')->get() // Eager loading
     * 
     */
    public function posts() {
        return $this->hasMany(Post::class);
    }

    /**
     * Get the favorites for the user.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     * 
     * @example | $user->favorites // Access the related favorites
     * @example | User::with('favorites')->get() // Eager loading
     */
    public function favorites() {
        return $this->hasMany(UserFavorite::class);
    }

    /**
     * Get the profile for the user.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     * 
     * @example | $user->profile // Access the related profile
     * @example | User::with('profile')->get() // Eager loading
     */
    public function profile() {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * Get all reports received by this user
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     * 
     * @example | $user->reports // Access the related reports
     * @example | User::with('reports')->get() // Eager loading
     */
    public function reportsReceived() {
        return $this->morphMany(UserReport::class, 'reportable');
    }

    /**
     * Get all reports created by this user
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     * 
     * @example | $user->reportsSent // Access the related reports
     * @example | User::with('reportsSent')->get() // Eager loading
     */
    public function reportsSent() {
        return $this->hasMany(UserReport::class, 'user_id');
    }

    /**
     * Get all comments created by this user
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     * 
     * @example | $user->comments // Access the related comments
     * @example | User::with('comments')->get() // Eager loading
     */
    public function comments() {
        return $this->hasMany(Comment::class);
    }

    /**
     * Get all likes created by this user
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     * 
     * @example | $user->likes // Access the related likes
     * @example | User::with('likes')->get() // Eager loading
     */
    public function likes() {
        return $this->hasMany(UserLike::class);
    }

    /**
     * Get the follower relations for this user.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     * 
     * @example | $user->followerRelations // Access the related followers
     * @example | User::with('followerRelations')->get() // Eager loading
     */
    public function followerRelations() {
        return $this->hasMany(UserFollower::class, 'user_id');
    }

    /**
     * Get the following relations for this user.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     * 
     * @example | $user->followingRelations // Access the related followings
     * @example | User::with('followingRelations')->get() // Eager loading
     */
    public function followingRelations() {
        return $this->hasMany(UserFollower::class, 'follower_id');
    }

    /**
     * Get the forbidden names created by this user
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     * 
     * @example | $user->forbiddenNames // Access the related forbidden names
     * @example | User::with('forbiddenNames')->get() // Eager loading
     */
    public function forbiddenNames() {
        return $this->hasMany(ForbiddenName::class, 'created_by_user_id');
    }

    /**
     * Get the post allowed values created by this user
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     * 
     * @example | $user->postAllowedValues // Access the related post allowed values
     * @example | User::with('postAllowedValues')->get() // Eager loading
     */
    public function postAllowedValues() {
        return $this->hasMany(PostAllowedValue::class, 'created_by_user_id');
    }
}
