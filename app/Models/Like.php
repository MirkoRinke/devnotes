<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Like extends Model {

    /**
     *  The traits used in the model
     */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'likeable_type',
        'likeable_id',
        'type',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'user_id' => 'integer',
        'likeable_id' => 'integer',
    ];

    /**
     * Get the user that owns the like
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user() {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the owning likeable model
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function likeable() {
        return $this->morphTo();
    }
}
