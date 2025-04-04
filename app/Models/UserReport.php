<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserReport extends Model {
    use HasFactory;

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
        'reportable_type',
        'reportable_id',
        'type',
        'reason',
        'impact_value'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'impact_value' => 'integer',
    ];

    /**
     * Get the user who created the report
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user() {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the entity that was reported (Post or User)
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function reportable() {
        return $this->morphTo();
    }
}
