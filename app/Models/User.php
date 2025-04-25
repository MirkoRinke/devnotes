<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Laravel\Sanctum\HasApiTokens;

use App\Models\Post;
use App\Models\UserFavorite;



class User extends Authenticatable {
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        // Default
        'id',
        'name',
        'email',
        'email_verified_at',
        'password',
        'created_at',
        'updated_at',

        // Basic
        'display_name',
        'role',

        // Ban info
        'is_banned',
        'was_ever_banned',

        // Moderation info
        'moderation_info',

        // Account info
        'account_purpose',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
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
     */
    public function posts() {
        return $this->hasMany(Post::class);
    }

    /**
     * Get the favorites for the user.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function favorites() {
        return $this->hasMany(UserFavorite::class);
    }

    /**
     * Get the profile for the user.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function profile() {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * Get all reports received by this user
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function reportsReceived() {
        return $this->morphMany(UserReport::class, 'reportable');
    }

    /**
     * Get all reports created by this user
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function reportsSent() {
        return $this->hasMany(UserReport::class, 'user_id');
    }

    /**
     * Get all comments created by this user
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function comments() {
        return $this->hasMany(Comment::class);
    }

    /**
     * Get all likes created by this user
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function likes() {
        return $this->hasMany(UserLike::class);
    }

    /**
     * Get the follower relations for this user.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function followerRelations() {
        return $this->hasMany(UserFollower::class, 'user_id');
    }

    /**
     * Get the following relations for this user.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function followingRelations() {
        return $this->hasMany(UserFollower::class, 'follower_id');
    }

    /**
     * Get the forbidden names created by this user
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function forbiddenNames() {
        return $this->hasMany(ForbiddenName::class, 'created_by_user_id');
    }

    /**
     * Get the post allowed values created by this user
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function postAllowedValues() {
        return $this->hasMany(PostAllowedValue::class, 'created_by_user_id');
    }
}
