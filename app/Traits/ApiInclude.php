<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait ApiInclude {
    /**
     * Check if the request has an 'include' parameter
     * If so, make the relations visible
     *
     * @param Request $request The HTTP request containing query parameters
     * @param \Illuminate\Database\Eloquent\Builder $query The base query to configure
     */
    function checkForIncludedRelations(Request $request, $target) {
        if ($request->has('include')) {
            $relations = explode(',', $request->input('include'));

            if (method_exists($target, 'makeVisible')) {
                $target->makeVisible($relations);
            }

            $select = $this->getSelectFields($request);

            if (in_array('children', $relations) && isset($target->children)) {
                foreach ($target->children as $child) {
                    if (method_exists($child, 'setVisible')) {
                        $child->setVisible($select ?? []);
                    }
                    if (method_exists($child, 'makeVisible')) {
                        $child->makeVisible($relations);
                    }
                }
            }
        }
        return $target;
    }
}
