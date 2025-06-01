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
        // 'id',                    || Laravel will automatically handle the 'id' field
        // 'created_at',            || Laravel will automatically handle the 'created_at' field
        // Basic
        'name',
        'language',
        'severity',
        // 'created_by_role',       || Explicitly set in controller from authenticated user's role
        // 'created_by_user_id',    || Explicitly set in controller from authenticated user's id

        // Update info
        // 'updated_at',            || Laravel will automatically handle the 'updated_at' field
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
