<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ForbiddenName extends Model {

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
        'id',

        // Basic
        'name',
        'match_type',
        'created_by_role',
        'created_by_user_id',

        // Update info
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        // Relationships
        'user',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * Get the user who created this forbidden name
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * 
     * @example | $forbiddenName->creator // Access the creator of the forbidden name
     * @example | ForbiddenName::with('creator')->get() // Eager loading
     */
    public function creator() {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get the user who created this forbidden name
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * 
     * @example | $forbiddenName->user // Access the user of the forbidden name
     * @example | ForbiddenName::with('user')->get() // Eager loading
     */
    public function user() {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
