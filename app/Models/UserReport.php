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
        // 'id',                            || Laravel will automatically handle the 'id' field 
        // 'created_at',                    || Laravel will automatically handle the 'created_at' field 

        // Basic
        // 'user_id',                       || Automatically created in the controller
        // 'reportable_type',               || Automatically created in the controller
        // 'reportable_id',                 || Automatically created in the controller
        // 'reason',                        || Automatically created in the controller
        // 'type',                          || Automatically created in the controller
        // 'reportable_snapshot',           || Automatically created in the controller
        // 'impact_value',                  || Automatically created in the controller

        // Update info
        // 'updated_at',                    || Laravel will automatically handle the 'updated_at' field 
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
