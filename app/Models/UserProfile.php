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
        // Default
        'id',
        'created_at',
        'updated_at',

        // Basic
        'user_id',
        'display_name',
        'public_email',
        'website',
        'avatar_path',
        'is_public',
        'location',
        'biography',
        'skills',
        'social_links',
        'contact_channels',

        // Settings
        'auto_load_external_images',
        'external_images_temp_until',

        'auto_load_external_videos',
        'external_videos_temp_until',

        'auto_load_external_resources',
        'external_resources_temp_until',

        // Counts
        'reports_count',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'user',
        'reports_count',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        // Default
        'is_public' => 'boolean',
        'skills' => 'array',
        'social_links' => 'array',
        'contact_channels' => 'array',

        // Settings
        'auto_load_external_images' => 'boolean',
        'external_images_temp_until' => 'datetime',

        'auto_load_external_videos' => 'boolean',
        'external_videos_temp_until' => 'datetime',

        'auto_load_external_resources' => 'boolean',
        'external_resources_temp_until' => 'datetime',

        // Counts
        'reports_count' => 'integer',
    ];


    /**
     * Get the user that owns the profile.
     */
    public function user() {
        return $this->belongsTo(User::class);
    }
}
