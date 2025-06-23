<?php

namespace App\Traits;

use Illuminate\Http\Request;

use App\Models\CriticalTerm;
use App\Models\UserProfile;
use App\Models\Post;
use App\Models\Comment;
use App\Models\UserReport;

trait ReportHelper {

    /**
     * Check for critical terms in the reason
     * 
     * @param string $reason The report reason to check
     * @return int The severity level
     * 
     * @example | $this->checkCriticalTerms($reason);
     */
    private function checkCriticalTerms($reason) {
        // Default severity if no critical terms are found
        $defaultSeverity = 1;

        if (empty($reason)) {
            return $defaultSeverity;
        }

        // Generate a cache key for the critical terms
        $cacheKey = $this->generateSimpleCacheKey('critical_terms');

        // Get all critical terms from the database
        // Cache the critical terms for 1 hour
        $criticalTerms = $this->cacheData($cacheKey, 3600, function () {
            return CriticalTerm::all();
        });

        // Check if any critical term is found in the reason
        foreach ($criticalTerms as $name) {
            if (stripos($reason, $name->name) !== false) {
                return $name->severity;
            }
        }

        return $defaultSeverity;
    }


    /**
     * Check if the user can report the entity
     * 
     * @param User $user The user who is reporting
     * @param string $reportableType The type of the reportable entity (Post, UserProfile, Comment)
     * @param mixed $reportable The reportable entity
     * @param int $reportableId The ID of the reportable entity
     * @param string $simpleType The simple type of the reportable entity (post, userProfile, comment)
     * @return JsonResponse|null
     * 
     * @example | $validationResult = $this->reportValidationCheck($user, $reportableType, $reportable, $reportableId, $simpleType);
     *            if ($validationResult !== null) {
     *             return $validationResult;
     *          }
     */
    protected function reportValidationCheck($user, $reportableType, $reportable, $reportableId, $simpleType) {
        if ($reportableType === UserProfile::class && $reportable->user_id == $user->id) {
            return $this->errorResponse('You cannot report yourself', 'CANNOT_REPORT_SELF', 403);
        } else if ($reportableType === Post::class && $reportable->user_id == $user->id) {
            return $this->errorResponse('You cannot report your own post', 'CANNOT_REPORT_OWN_POST', 403);
        } else if ($reportableType === Comment::class && $reportable->user_id == $user->id) {
            return $this->errorResponse('You cannot report your own comment', 'CANNOT_REPORT_OWN_COMMENT', 403);
        }

        $existingReport = UserReport::where([
            'user_id' => $user->id,
            'reportable_id' => $reportableId,
            'reportable_type' => $reportableType
        ])->first();

        if ($existingReport) {
            return $this->errorResponse('You have already reported this ' . $simpleType, 'ALREADY_REPORTED',  409);
        }

        return null;
    }
}
