<?php

namespace App\Traits;

use Illuminate\Http\Request;

use App\Models\Post;

/**
 * PostAllowedValueHelper
 *
 * This trait provides helper methods for working with Post Allowed Values.
 */
trait PostAllowedValueHelper {

    /**
     * Check if the Post Allowed Value is used in any posts
     * 
     * @param string $name The name of the allowed value
     * @param string $type The type of the allowed value (e.g., category, post_type, status)
     * @return bool True if the value is in use, false otherwise
     * 
     * @example | $isInUse = $this->isPostAllowedValueInUse($name, $type)
     */
    protected function isPostAllowedValueInUse($name, $type) {
        $isInUse = false;

        switch ($type) {
            case 'category':
                $isInUse = Post::where('category', $name)->exists();
                break;
            case 'post_type':
                $isInUse = Post::where('post_type', $name)->exists();
                break;
            case 'status':
                $isInUse = Post::where('status', $name)->exists();
                break;
        }
        return $isInUse;
    }
}
