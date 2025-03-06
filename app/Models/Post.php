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
        'user_id',
        'title',
        'code',
        'description', 
        'resources', 
        'language', 
        'category', 
        'tags',
        'status',
        'favorite_count'
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
}
