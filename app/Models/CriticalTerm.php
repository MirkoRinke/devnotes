<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CriticalTerm extends Model {

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
        'language',
        'severity',
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
    protected $casts = [
        // Basic
        'severity' => 'integer',
    ];


    /**
     * Get the user who created the critical term.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * 
     * @example | $criticalTerm->user // Access the user of the post allowed value
     * @example | CriticalTerm::with('user')->get() // Eager loading
     */
    public function user() {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
