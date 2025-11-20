<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProfileFavoriteTechs extends Model {
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_profile_id',
        'post_allowed_value_id',
    ];

    /**
     * Get the user profile that owns this favorite language relation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function userProfile() {
        return $this->belongsTo(UserProfile::class);
    }

    /**
     * Get the language value for this relation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function favoriteTech() {
        return $this->belongsTo(PostAllowedValue::class, 'post_allowed_value_id')->whereIn('type', ['language', 'technology']);
    }
}
