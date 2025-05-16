<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserReport extends Model {

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
        'user_id',
        'reportable_type',
        'reportable_id',
        'type',
        'reason',
        'reportable_snapshot',
        'impact_value',

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
        'reportable'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        // Basic
        'reportable_snapshot' => 'array',
        'impact_value' => 'integer',
    ];

    /**
     * Get the user who created the report
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * 
     * @example | $userReport->user // Access the related user
     * @example | User::with('reports')->get() // Eager loading
     */
    public function user() {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the entity that was reported (Post or User)
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     * 
     * @example $userReport->reportable // Access the related reportable entity
     * @example UserReport::with('reportable')->get() // Eager loading
     */
    public function reportable() {
        return $this->morphTo();
    }
}
