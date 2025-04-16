<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Collection;

trait ApiResponses {

    /**
     * Success response method for returning data, message and status code
     *
     * @param mixed $data
     * @param string|null $message
     * @param int $code
     * @return JsonResponse
     */
    protected function successResponse($data, $message = null, $code = 200): JsonResponse {
        if ($data instanceof Collection) {
            $count = $data->count();
        } else {
            $count = 1;
        }

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'code' => $code,
            'count' => $count,
            'data' => $data
        ], $code);
    }


    /**
     * Error response method for returning error message, errors and status code
     *
     * @param string $message
     * @param array $errors
     * @param int $code
     * @return JsonResponse
     */
    protected function errorResponse($message, $errors = [], $code): JsonResponse {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'code' => $code,
            'errors' => $errors
        ], $code);
    }

    /**
     * Validation error response method for returning validation errors
     *
     * @param array $errors
     * @return JsonResponse
     */
    protected function getValidationMessages(): array {
        return [
            // User validation messages
            'name.required' => 'NAME_FIELD_REQUIRED',
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
            'password.min' => 'PASSWORD_TOO_SHORT',
            'password.confirmed' => 'PASSWORD_CONFIRMATION_MISMATCH',
            'days.required' => 'DAYS_FIELD_REQUIRED',
            'days.integer' => 'DAYS_MUST_BE_INTEGER',
            'days.min' => 'DAYS_MUST_BE_AT_LEAST_1',
            'days.max' => 'DAYS_CANNOT_EXCEED_99999',


            // Post validation messages
            'title.required' => 'TITLE_FIELD_REQUIRED',
            'title.string' => 'TITLE_MUST_BE_STRING',
            'title.max' => 'TITLE_FIELD_MAX_LENGTH',
            'code.required' => 'CODE_FIELD_REQUIRED',
            'code.string' => 'CODE_MUST_BE_STRING',
            'description.required' => 'DESCRIPTION_FIELD_REQUIRED',
            'description.string' => 'DESCRIPTION_MUST_BE_STRING',
            'images.array' => 'IMAGES_MUST_BE_ARRAY',
            'images.*.url' => 'IMAGES_MUST_BE_VALID_URLS',
            'images.*.max' => 'IMAGES_URL_TOO_LONG',
            'videos.array' => 'VIDEOS_MUST_BE_ARRAY',
            'videos.*.url' => 'VIDEOS_MUST_BE_VALID_URLS',
            'videos.*.max' => 'VIDEOS_URL_TOO_LONG',
            'resources.array' => 'RESOURCES_MUST_BE_ARRAY',
            'resources.*.url' => 'RESOURCES_MUST_BE_VALID_URLS',
            'resources.*.string' => 'RESOURCES_MUST_BE_STRING',
            'language.required' => 'LANGUAGE_FIELD_REQUIRED',
            'language.string' => 'LANGUAGE_MUST_BE_STRING',
            'language.max' => 'LANGUAGE_FIELD_MAX_LENGTH',
            'category.required' => 'CATEGORY_FIELD_REQUIRED',
            'category.string' => 'CATEGORY_MUST_BE_STRING',
            'category.max' => 'CATEGORY_FIELD_MAX_LENGTH',
            'tags.required' => 'TAGS_FIELD_REQUIRED',
            'tags.array' => 'TAGS_MUST_BE_ARRAY',
            'status.required' => 'STATUS_FIELD_REQUIRED',
            'status.in' => 'STATUS_INVALID_OPTION',
            'moderation_reason.required' => 'MODERATION_REASON_FIELD_REQUIRED',
            'moderation_reason.string' => 'MODERATION_REASON_MUST_BE_STRING',
            'moderation_reason.max' => 'MODERATION_REASON_FIELD_MAX_LENGTH',


            // User Profile validation messages  
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
            'avatar_path.string' => 'AVATAR_PATH_MUST_BE_STRING',
            'avatar_path.max' => 'AVATAR_PATH_FIELD_MAX_LENGTH',
            'is_public.required' => 'IS_PUBLIC_FIELD_REQUIRED',
            'is_public.boolean' => 'IS_PUBLIC_MUST_BE_BOOLEAN',
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


            // User Report validation messages
            'reportable_type.required' => 'REPORTABLE_TYPE_FIELD_REQUIRED',
            'reportable_type.in' => 'REPORTABLE_TYPE_INVALID_OPTION',
            'reportable_id.required' => 'REPORTABLE_ID_FIELD_REQUIRED',
            'reportable_id.integer' => 'REPORTABLE_ID_MUST_BE_INTEGER',
            'reason.string' => 'REASON_MUST_BE_STRING',
            'reason.max' => 'REASON_FIELD_MAX_LENGTH',


            // Like validation messages
            'likeable_type.required' => 'LIKEABLE_TYPE_FIELD_REQUIRED',
            'likeable_type.in' => 'LIKEABLE_TYPE_INVALID_OPTION',
            'likeable_id.required' => 'LIKEABLE_ID_FIELD_REQUIRED',
            'likeable_id.integer' => 'LIKEABLE_ID_MUST_BE_INTEGER',


            // Comment validation messages
            'content.required' => 'CONTENT_FIELD_REQUIRED',
            'content.string' => 'CONTENT_MUST_BE_STRING',
            'content.max' => 'CONTENT_FIELD_MAX_LENGTH',
            'post_id.required' => 'POST_ID_FIELD_REQUIRED',
            'post_id.exists' => 'POST_ID_NOT_FOUND',
            'parent_id.exists' => 'PARENT_ID_NOT_FOUND',

        ];
    }
}
