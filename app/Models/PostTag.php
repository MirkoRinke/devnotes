<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostTag extends Model {
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'post_id',
        'post_allowed_value_id',
    ];

    /**
     * Get the post that owns this tag relation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function post() {
        return $this->belongsTo(Post::class);
    }

    /**
     * Get the tag value for this relation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tag() {
        return $this->belongsTo(PostAllowedValue::class, 'post_allowed_value_id')->where('type', 'tag');
    }
}
