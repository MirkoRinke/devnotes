<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model {

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
        // 'id',                                || Laravel will automatically handle the 'id' field
        // 'created_at',                        || Laravel will automatically handle the 'created_at' field 

        // Basic
        // 'user_id',                           || Set when profile is created during user registration
        'display_name',
        'public_email',
        'website',
        'is_public',
        'location',
        'biography',
        'skills',
        'social_links',
        'contact_channels',

        // Settings
        'preferred_theme',
        'preferred_language',

        'auto_load_external_images',
        // 'external_images_temp_until',        || Explicitly set in controller via enableTemporaryExternals

        'auto_load_external_videos',
        // 'external_videos_temp_until',        || Explicitly set in controller via enableTemporaryExternals

        'auto_load_external_resources',
        // 'external_resources_temp_until',     || Explicitly set in controller via enableTemporaryExternals

        // Counts
        // 'reports_count',                     || Automatically handled by the UserReport model

        // Update info
        // 'updated_at',                        || Laravel will automatically handle the 'updated_at' field
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
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * 
     * @example | $userProfile->user // Access the related user
     * @example | UserProfile::with('user')->get() // Eager loading
     */
    public function user() {
        return $this->belongsTo(User::class);
    }


    /**
     * Get the favorite languages of the user profile.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     * 
     * @example | $userProfile->favoriteTechs // Access the related favorite technologies
     * @example | UserProfile::with('favoriteTechs')->get() // Eager loading
     */
    public function favoriteTechs() {
        return $this->belongsToMany(
            PostAllowedValue::class,
            'user_profile_favorite_techs',
            'user_profile_id',
            'post_allowed_value_id'
        );
    }
}
