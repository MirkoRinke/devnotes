<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * This ApiResponses Trait provides methods for creating standardized API responses.
 * It includes methods for success and error responses, as well as validation error messages.
 */
trait ApiResponses {

    /**
     * Create a success response with standardized format
     *
     * @param mixed $data The data to return in the response
     * @param string|null $message A descriptive message about the operation
     * @param int $code HTTP status code (default: 200)
     * @return \Illuminate\Http\JsonResponse
     * 
     * @example | return $this->successResponse($query, 'Users retrieved successfully', 200);
     */
    protected function successResponse($data, $message = null, $code = 200): JsonResponse {

        $isPaginator = $data instanceof LengthAwarePaginator;
        $isCollection = $data instanceof Collection;

        if ($isPaginator || $isCollection) {
            $count = $data->count();
        } else if ($data === null || (is_array($data) && empty($data)) || (is_string($data) && trim($data) === '')) {
            $count = 0;
        } else {
            $count = 1;
        }

        $response = [
            'status' => 'success',
            'message' => $message,
            'code' => $code,
            'count' => $count
        ];

        // If query logging is enabled, add the query log to the response
        if (env('QUERY_LOGGING_ENABLED', false)) {
            $queryLog = DB::getQueryLog();
            $response['meta'] = [
                'total_queries' => count($queryLog),
            ];
            if (env('QUERY_LOGGING_QUERIES_ENABLED', false)) {
                $response['meta']['queries'] =  $queryLog;
            }
        }

        /**
         * If the data is not a paginator, wrap it in a 'data' key.
         * This ensures that the response structure remains consistent for the frontend.
         */
        if (!$isPaginator) {
            $data = ['data' => $data];
        }

        $response['data'] = $data;

        return response()->json($response, $code);
    }


    /**
     * Create an error response with standardized format
     * 
     * @param string $message Error message
     * @param mixed $errors Detailed error information or error code
     * @param int $code HTTP status code
     * @return \Illuminate\Http\JsonResponse
     * 
     * @example | return $this->errorResponse("User with ID $id does not exist", 'USER_NOT_FOUND', 404);
     */
    protected function errorResponse($message, $errors = [], $code): JsonResponse {
        $response = [
            'status' => 'error',
            'message' => $message,
            'code' => $code,
            'errors' => $errors
        ];

        // If query logging is enabled, add the query log to the response
        if (env('QUERY_LOGGING_ENABLED', false)) {
            $queryLog = DB::getQueryLog();
            $response['meta'] = [
                'total_queries' => count($queryLog),
            ];
            if (env('QUERY_LOGGING_QUERIES_ENABLED', false)) {
                $response['meta']['queries'] =  $queryLog;
            }
        }

        return response()->json($response, $code);
    }

    /**
     * Get validation error messages for a specific model
     *
     * This method returns an array of validation messages based on the model type.
     * These messages use standardized error codes to make API responses consistent.
     *
     * @param string $model The model name to get validation messages for
     * @return array|null Array of validation messages or null if model not found
     * 
     * @example | $this->getValidationMessages('ApiKey')
     */
    protected function getValidationMessages(string $model): ?array {
        /**
         * Define the validation messages for specific models
         * This method returns an array of validation messages based on the model type.
         */
        switch ($model) {
            case 'ApiKey':
                return [
                    'name.required' => 'NAME_FIELD_REQUIRED',
                    'name.string' => 'NAME_MUST_BE_STRING',
                    'name.max' => 'NAME_FIELD_MAX_LENGTH',
                ];
            case 'Login':
                return [

                    'email.required' => 'EMAIL_FIELD_REQUIRED',
                    'email.string' => 'EMAIL_MUST_BE_STRING',
                    'email.email' => 'EMAIL_MUST_BE_VALID',

                    'user_name.required' => 'USERNAME_FIELD_REQUIRED',
                    'user_name.string' => 'USERNAME_MUST_BE_STRING',
                    'user_name.max' => 'USERNAME_FIELD_MAX_LENGTH',

                    'password.required' => 'PASSWORD_FIELD_REQUIRED',
                    'password.string' => 'PASSWORD_MUST_BE_STRING',

                    'device_name' => 'NAME_FIELD_REQUIRED',
                    'device_name.string' => 'NAME_MUST_BE_STRING',

                    'device_fingerprint' => 'DEVICE_FINGERPRINT_FIELD_REQUIRED',
                    'device_fingerprint.string' => 'DEVICE_FINGERPRINT_MUST_BE_STRING',
                    'device_fingerprint.max' => 'DEVICE_FINGERPRINT_FIELD_MAX_LENGTH_255',

                    'privacy_policy_accepted' => 'PRIVACY_POLICY_ACCEPTED_FIELD_REQUIRED',
                    'privacy_policy_accepted.accepted' => 'PRIVACY_POLICY_ACCEPTED_MUST_BE_TRUE',
                ];
            case 'verifyEmail':
                return [
                    'id.required' => 'ID_FIELD_REQUIRED',
                    'id.integer' => 'ID_MUST_BE_INTEGER',

                    'hash.required' => 'HASH_FIELD_REQUIRED',
                    'hash.string' => 'HASH_MUST_BE_STRING',
                ];
            case 'ForgotPassword':
                return [
                    'email.required' => 'EMAIL_FIELD_REQUIRED',
                    'email.string' => 'EMAIL_MUST_BE_STRING',
                    'email.email' => 'EMAIL_MUST_BE_VALID',
                ];
            case 'ResetPassword':
                return [
                    'token.required' => 'TOKEN_FIELD_REQUIRED',
                    'token.string' => 'TOKEN_MUST_BE_STRING',

                    'email.required' => 'EMAIL_FIELD_REQUIRED',
                    'email.string' => 'EMAIL_MUST_BE_STRING',
                    'email.email' => 'EMAIL_MUST_BE_VALID',

                    'password.required' => 'PASSWORD_FIELD_REQUIRED',
                    'password.string' => 'PASSWORD_MUST_BE_STRING',
                    'password.min' => 'PASSWORD_TOO_SHORT',
                ];
            case 'User':
                return [
                    'name.required' => 'NAME_FIELD_REQUIRED',
                    'name.unique' => 'NAME_ALREADY_IN_USE',
                    'name.string' => 'NAME_MUST_BE_STRING',
                    'name.min' => 'NAME_FIELD_MIN_LENGTH',
                    'name.max' => 'NAME_FIELD_MAX_LENGTH',

                    'display_name.required' => 'DISPLAY_NAME_FIELD_REQUIRED',
                    'display_name.unique' => 'DISPLAY_NAME_ALREADY_IN_USE',
                    'display_name.string' => 'DISPLAY_NAME_MUST_BE_STRING',
                    'display_name.max' => 'DISPLAY_NAME_FIELD_MAX_LENGTH',

                    'email.required' => 'EMAIL_FIELD_REQUIRED',
                    'email.string' => 'EMAIL_MUST_BE_STRING',
                    'email.email' => 'EMAIL_MUST_BE_VALID',
                    'email.unique' => 'EMAIL_ALREADY_IN_USE',

                    'password.required' => 'PASSWORD_FIELD_REQUIRED',
                    'password.string' => 'PASSWORD_MUST_BE_STRING',
                    'password.confirmed' => 'PASSWORD_CONFIRMATION_MISMATCH',
                    'password.min' => 'PASSWORD_TOO_SHORT',
                    'password.max' => 'PASSWORD_TOO_LONG',
                    'password.mixed' => 'PASSWORD_MUST_HAVE_MIXED_CASE',
                    'password.letters' => 'PASSWORD_MUST_HAVE_LETTERS',
                    'password.numbers' => 'PASSWORD_MUST_HAVE_NUMBERS',
                    'password.symbols' => 'PASSWORD_MUST_HAVE_SYMBOLS',
                    'password.uncompromised' => 'PASSWORD_MUST_BE_UNCOMPROMISED',

                    'avatar_items.array' => 'AVATAR_ITEMS_MUST_BE_ARRAY',

                    'avatar_items.duck.string' => 'DUCK_MUST_BE_STRING',
                    'avatar_items.duck.starts_with' => 'DUCK_MUST_START_WITH_CORRECT_PATH',
                    'avatar_items.duck.ends_with' => 'DUCK_MUST_END_WITH_WEBP',

                    'avatar_items.head_accessory.string' => 'HEAD_ACCESSORY_MUST_BE_STRING',
                    'avatar_items.head_accessory.starts_with' => 'HEAD_ACCESSORY_MUST_START_WITH_CORRECT_PATH',
                    'avatar_items.head_accessory.ends_with' => 'HEAD_ACCESSORY_MUST_END_WITH_WEBP',

                    'avatar_items.eye_accessory.string' => 'EYE_ACCESSORY_MUST_BE_STRING',
                    'avatar_items.eye_accessory.starts_with' => 'EYE_ACCESSORY_MUST_START_WITH_CORRECT_PATH',
                    'avatar_items.eye_accessory.ends_with' => 'EYE_ACCESSORY_MUST_END_WITH_WEBP',

                    'avatar_items.ear_accessory.string' => 'EAR_ACCESSORY_MUST_BE_STRING',
                    'avatar_items.ear_accessory.starts_with' => 'EAR_ACCESSORY_MUST_START_WITH_CORRECT_PATH',
                    'avatar_items.ear_accessory.ends_with' => 'EAR_ACCESSORY_MUST_END_WITH_WEBP',

                    'avatar_items.neck_accessory.string' => 'NECK_ACCESSORY_MUST_BE_STRING',
                    'avatar_items.neck_accessory.starts_with' => 'NECK_ACCESSORY_MUST_START_WITH_CORRECT_PATH',
                    'avatar_items.neck_accessory.ends_with' => 'NECK_ACCESSORY_MUST_END_WITH_WEBP',

                    'avatar_items.chest_accessory.string' => 'CHEST_ACCESSORY_MUST_BE_STRING',
                    'avatar_items.chest_accessory.starts_with' => 'CHEST_ACCESSORY_MUST_START_WITH_CORRECT_PATH',
                    'avatar_items.chest_accessory.ends_with' => 'CHEST_ACCESSORY_MUST_END_WITH_WEBP',

                    'avatar_items.background.string' => 'BACKGROUND_MUST_BE_STRING',
                    'avatar_items.background.starts_with' => 'BACKGROUND_MUST_START_WITH_CORRECT_PATH',
                    'avatar_items.background.ends_with' => 'BACKGROUND_MUST_END_WITH_WEBP',

                    'privacy_policy_accepted' => 'PRIVACY_POLICY_ACCEPTED_FIELD_REQUIRED',
                    'privacy_policy_accepted.accepted' => 'PRIVACY_POLICY_ACCEPTED_MUST_BE_TRUE',

                    'terms_of_service_accepted' => 'TERMS_OF_SERVICE_ACCEPTED_FIELD_REQUIRED',
                    'terms_of_service_accepted.accepted' => 'TERMS_OF_SERVICE_ACCEPTED_MUST_BE_TRUE',

                    'days.required' => 'DAYS_FIELD_REQUIRED',
                    'days.integer' => 'DAYS_MUST_BE_INTEGER',
                    'days.min' => 'DAYS_MUST_BE_AT_LEAST_1',
                    'days.max' => 'DAYS_CANNOT_EXCEED_99999',
                ];
            case 'Post':
                return [
                    'title.required' => 'TITLE_FIELD_REQUIRED',
                    'title.string' => 'TITLE_MUST_BE_STRING',
                    'title.max' => 'TITLE_FIELD_MAX_LENGTH',

                    'code.required' => 'CODE_FIELD_REQUIRED',
                    'code.string' => 'CODE_MUST_BE_STRING',
                    'code.max' => 'CODE_FIELD_MAX_LENGTH',

                    'description.required' => 'DESCRIPTION_FIELD_REQUIRED',
                    'description.string' => 'DESCRIPTION_MUST_BE_STRING',
                    'description.min' => 'DESCRIPTION_FIELD_MIN_LENGTH',
                    'description.max' => 'DESCRIPTION_FIELD_MAX_LENGTH',

                    'images.array' => 'IMAGES_MUST_BE_ARRAY',
                    'images.*.url' => 'IMAGES_MUST_BE_VALID_URLS',
                    'images.*.max' => 'IMAGES_URL_TOO_LONG',

                    'videos.array' => 'VIDEOS_MUST_BE_ARRAY',
                    'videos.*.url' => 'VIDEOS_MUST_BE_VALID_URLS',
                    'videos.*.max' => 'VIDEOS_URL_TOO_LONG',

                    'resources.array' => 'RESOURCES_MUST_BE_ARRAY',
                    'resources.*.url' => 'RESOURCES_MUST_BE_VALID_URLS',
                    'resources.*.string' => 'RESOURCES_MUST_BE_STRING',

                    'languages.required' => 'LANGUAGE_FIELD_REQUIRED',
                    'languages.required_without' => 'LANGUAGE_OR_TECHNOLOGY_FIELD_REQUIRED',
                    'languages.array' => 'LANGUAGE_MUST_BE_ARRAY',
                    'languages.*.string' => 'LANGUAGE_MUST_BE_STRING',
                    'languages.*.required' => 'LANGUAGE_FIELD_REQUIRED',
                    'languages.*.ValidPostValue' => 'LANGUAGE_INVALID_OPTION',


                    'category.required' => 'CATEGORY_FIELD_REQUIRED',
                    'category.string' => 'CATEGORY_MUST_BE_STRING',
                    'category.in' => 'CATEGORY_INVALID_OPTION',

                    'post_type.required' => 'POST_TYPE_FIELD_REQUIRED',
                    'post_type.string' => 'POST_TYPE_MUST_BE_STRING',
                    'post_type.in' => 'POST_TYPE_INVALID_OPTION',

                    'technologies.required' => 'TECHNOLOGY_FIELD_REQUIRED',
                    'technologies.required_without' => 'LANGUAGE_OR_TECHNOLOGY_FIELD_REQUIRED',
                    'technologies.array' => 'TECHNOLOGY_MUST_BE_ARRAY',
                    'technologies.*.string' => 'TECHNOLOGY_MUST_BE_STRING',
                    'technologies.*.required' => 'TECHNOLOGY_FIELD_REQUIRED',
                    'technologies.*.ValidPostValue' => 'TECHNOLOGY_INVALID_OPTION',

                    'tags.array' => 'TAGS_MUST_BE_ARRAY',
                    'tags.*.string' => 'TAG_MUST_BE_STRING',
                    'tags.*.max' => 'TAG_FIELD_MAX_LENGTH',

                    'status.required' => 'STATUS_FIELD_REQUIRED',
                    'status.string' => 'STATUS_MUST_BE_STRING',
                    'status.in' => 'STATUS_INVALID_OPTION',

                    'syntax_highlighting.string' => 'SYNTAX_HIGHLIGHTING_MUST_BE_STRING',
                    'syntax_highlighting.required_with' => 'SYNTAX_HIGHLIGHTING_REQUIRED_WITH_LANGUAGES',
                    'syntax_highlighting.ValidPostValue' => 'SYNTAX_HIGHLIGHTING_INVALID_OPTION',

                    'moderation_reason.required' => 'MODERATION_REASON_FIELD_REQUIRED',
                    'moderation_reason.string' => 'MODERATION_REASON_MUST_BE_STRING',
                    'moderation_reason.max' => 'MODERATION_REASON_FIELD_MAX_LENGTH',
                ];
            case 'PostInteractions':
                return [
                    'type.required' => 'TYPE_FIELD_REQUIRED',
                    'type.string' => 'TYPE_MUST_BE_STRING',
                    'type.in' => 'TYPE_INVALID_OPTION',

                    'period.string' => 'PERIOD_MUST_BE_STRING',
                    'period.in' => 'PERIOD_INVALID_OPTION',
                ];
            case 'TopUsersByPostInteractions':
                return [
                    'type.required' => 'TYPE_FIELD_REQUIRED',
                    'type.string' => 'TYPE_MUST_BE_STRING',
                    'type.in' => 'TYPE_INVALID_OPTION',

                    'period.string' => 'PERIOD_MUST_BE_STRING',
                    'period.in' => 'PERIOD_INVALID_OPTION',

                    'setLimit.integer' => 'LIMIT_MUST_BE_INTEGER',
                    'setLimit.min' => 'LIMIT_MUST_BE_AT_LEAST_1',
                    'setLimit.max' => 'LIMIT_CANNOT_EXCEED_100',
                ];
            case 'UserProfile':
                return [
                    'user_id.required' => 'USER_ID_FIELD_REQUIRED',
                    'user_id.integer' => 'USER_ID_MUST_BE_INTEGER',

                    'display_name.required' => 'DISPLAY_NAME_FIELD_REQUIRED',
                    'display_name.unique' => 'DISPLAY_NAME_ALREADY_IN_USE',
                    'display_name.string' => 'DISPLAY_NAME_MUST_BE_STRING',
                    'display_name.min' => 'DISPLAY_NAME_FIELD_MIN_LENGTH',
                    'display_name.max' => 'DISPLAY_NAME_FIELD_MAX_LENGTH',

                    'public_email.email' => 'PUBLIC_EMAIL_MUST_BE_VALID',
                    'public_email.max' => 'PUBLIC_EMAIL_FIELD_MAX_LENGTH',

                    'location.string' => 'LOCATION_MUST_BE_STRING',
                    'location.max' => 'LOCATION_FIELD_MAX_LENGTH',

                    'skills.array' => 'SKILLS_MUST_BE_ARRAY',

                    'biography.string' => 'BIOGRAPHY_MUST_BE_STRING',

                    'contact_channels.array' => 'CONTACT_CHANNELS_MUST_BE_ARRAY',

                    'social_links.array' => 'SOCIAL_LINKS_MUST_BE_ARRAY',

                    'website.string' => 'WEBSITE_MUST_BE_STRING',
                    'website.max' => 'WEBSITE_FIELD_MAX_LENGTH',

                    'is_public.required' => 'IS_PUBLIC_FIELD_REQUIRED',
                    'is_public.boolean' => 'IS_PUBLIC_MUST_BE_BOOLEAN',

                    'preferred_theme.required' => 'PREFERRED_THEME_FIELD_REQUIRED',
                    'preferred_theme.string' => 'PREFERRED_THEME_MUST_BE_STRING',
                    'preferred_theme.in' => 'PREFERRED_THEME_INVALID_OPTION',

                    'preferred_language.required' => 'PREFERRED_LANGUAGE_FIELD_REQUIRED',
                    'preferred_language.string' => 'PREFERRED_LANGUAGE_MUST_BE_STRING',
                    'preferred_language.in' => 'PREFERRED_LANGUAGE_INVALID_OPTION',

                    'auto_load_external_images.required' => 'AUTO_LOAD_EXTERNAL_IMAGES_FIELD_REQUIRED',
                    'auto_load_external_images.boolean' => 'AUTO_LOAD_EXTERNAL_IMAGES_MUST_BE_BOOLEAN',

                    'auto_load_external_videos.required' => 'AUTO_LOAD_EXTERNAL_VIDEOS_FIELD_REQUIRED',
                    'auto_load_external_videos.boolean' => 'AUTO_LOAD_EXTERNAL_VIDEOS_MUST_BE_BOOLEAN',

                    'auto_load_external_resources.required' => 'AUTO_LOAD_EXTERNAL_RESOURCES_FIELD_REQUIRED',
                    'auto_load_external_resources.boolean' => 'AUTO_LOAD_EXTERNAL_RESOURCES_MUST_BE_BOOLEAN',

                    'external_images_temp_until.date' => 'EXTERNAL_IMAGES_TEMP_UNTIL_MUST_BE_DATE',
                    'external_videos_temp_until.date' => 'EXTERNAL_VIDEOS_TEMP_UNTIL_MUST_BE_DATE',
                    'external_resources_temp_until.date' => 'EXTERNAL_RESOURCES_TEMP_UNTIL_MUST_BE_DATE',

                    'hours.required' => 'HOURS_FIELD_REQUIRED',
                    'hours.integer' => 'HOURS_MUST_BE_INTEGER',
                    'hours.min' => 'HOURS_MUST_BE_AT_LEAST_0',
                    'hours.max' => 'HOURS_CANNOT_EXCEED_72',

                    'type.required' => 'TYPE_FIELD_REQUIRED',
                    'type.in' => 'TYPE_INVALID_OPTION',
                    'type.string' => 'TYPE_MUST_BE_STRING',

                    'favorite_techs.array' => 'FAVORITE_TECHS_MUST_BE_ARRAY',
                    'favorite_techs.min' => 'FAVORITE_TECHS_MUST_CONTAIN_AT_LEAST_1_ITEM',
                    'favorite_techs.*.string' => 'FAVORITE_TECH_MUST_BE_STRING',
                ];
            case 'UserReport':
                return [
                    'reportable_type.required' => 'REPORTABLE_TYPE_FIELD_REQUIRED',
                    'reportable_type.in' => 'REPORTABLE_TYPE_INVALID_OPTION',
                    'reportable_id.required' => 'REPORTABLE_ID_FIELD_REQUIRED',
                    'reportable_id.integer' => 'REPORTABLE_ID_MUST_BE_INTEGER',

                    'reason.string' => 'REASON_MUST_BE_STRING',
                    'reason.max' => 'REASON_FIELD_MAX_LENGTH',
                ];
            case 'UserLike':
                return [
                    'likeable_type.required' => 'LIKEABLE_TYPE_FIELD_REQUIRED',
                    'likeable_type.in' => 'LIKEABLE_TYPE_INVALID_OPTION',
                    'likeable_id.required' => 'LIKEABLE_ID_FIELD_REQUIRED',
                    'likeable_id.integer' => 'LIKEABLE_ID_MUST_BE_INTEGER',
                ];
            case 'Comment':
                return [
                    'content.required' => 'CONTENT_FIELD_REQUIRED',
                    'content.string' => 'CONTENT_MUST_BE_STRING',
                    'content.max' => 'CONTENT_FIELD_MAX_LENGTH',

                    'post_id.required' => 'POST_ID_FIELD_REQUIRED',
                    'post_id.exists' => 'POST_ID_NOT_FOUND',

                    'parent_id.exists' => 'PARENT_ID_NOT_FOUND',
                ];
            case 'ForbiddenName':
                return [
                    'name.required' => 'NAME_FIELD_REQUIRED',
                    'name.string' => 'NAME_MUST_BE_STRING',
                    'name.min' => 'NAME_FIELD_MIN_LENGTH',
                    'name.max' => 'NAME_FIELD_MAX_LENGTH',

                    'match_type.required' => 'MATCH_TYPE_FIELD_REQUIRED',
                    'match_type.string' => 'MATCH_TYPE_MUST_BE_STRING',
                    'match_type.in' => 'MATCH_TYPE_INVALID_OPTION',
                ];
            case 'PostAllowedValue':
                return [
                    'name.required' => 'NAME_FIELD_REQUIRED',
                    'name.string' => 'NAME_MUST_BE_STRING',
                    'name.min' => 'NAME_FIELD_MIN_LENGTH',
                    'name.max' => 'NAME_FIELD_MAX_LENGTH',

                    'type.required' => 'TYPE_FIELD_REQUIRED',
                    'type.string' => 'TYPE_MUST_BE_STRING',
                    'type.in' => 'TYPE_INVALID_OPTION',
                ];
            case 'CriticalTerm':
                return [
                    'name.required' => 'NAME_FIELD_REQUIRED',
                    'name.string' => 'NAME_MUST_BE_STRING',
                    'name.min' => 'NAME_FIELD_MIN_LENGTH',
                    'name.max' => 'NAME_FIELD_MAX_LENGTH',

                    'language.required' => 'LANGUAGE_FIELD_REQUIRED',
                    'language.string' => 'LANGUAGE_MUST_BE_STRING',
                    'language.min' => 'LANGUAGE_FIELD_MIN_LENGTH',
                    'language.max' => 'LANGUAGE_FIELD_MAX_LENGTH',

                    'severity.required' => 'SEVERITY_FIELD_REQUIRED',
                    'severity.integer' => 'SEVERITY_MUST_BE_INTEGER',
                    'severity.between' => 'SEVERITY_MUST_BE_BETWEEN_1_AND_5',
                ];
        }
        return null;
    }
}
