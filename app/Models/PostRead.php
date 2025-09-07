<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostRead extends Model {

    protected $fillable = [
        // Default
        // 'id',                        || Laravel will automatically handle the 'id' field

        // Basic
        'user_id',                      // Set in the PostController when marking a post as read
        'post_id',                      // Set in the PostController when marking a post as read

        // Update info
        // 'created_at',                || Laravel will automatically handle the 'created_at' field
        'updated_at',                   // Laravel will automatically handle the 'updated_at' field
    ];
}
