<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model {

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'display_name',
        'public_email',
        'location',
        'skills',
        'biography',
        'social_links',
        'contact_channels',
        'website',
        'avatar_path',
        'is_public',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'skills' => 'array',
        'social_links' => 'array',
        'contact_channels' => 'array',
        'is_public' => 'boolean',
        'user_id' => 'integer',
    ];


    /**
     * Get the user that owns the profile.
     */
    public function user() {
        return $this->belongsTo(User::class);
    }
}
