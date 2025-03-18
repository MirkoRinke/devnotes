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
        'name',
        'display_name',
        'email',
        'password',
        'role',
        'is_banned',
        'banned_at',
        'unbanned_at',
        'ban_reason',
        'unban_reason',
        'banned_by',
        'unbanned_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
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
}
