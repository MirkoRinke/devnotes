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
            'name.max' => 'NAME_FIELD_MAX_LENGTH',
            'email.required' => 'EMAIL_FIELD_REQUIRED',
            'email.string' => 'EMAIL_MUST_BE_STRING',
            'email.email' => 'EMAIL_MUST_BE_VALID',
            'email.unique' => 'EMAIL_ALREADY_IN_USE',
            'password.required' => 'PASSWORD_FIELD_REQUIRED',
            'password.string' => 'PASSWORD_MUST_BE_STRING',
            'password.min' => 'PASSWORD_TOO_SHORT',
            'password.confirmed' => 'PASSWORD_CONFIRMATION_MISMATCH',

            // Post validation messages
            'title.required' => 'TITLE_FIELD_REQUIRED',
            'title.string' => 'TITLE_MUST_BE_STRING',
            'title.max' => 'TITLE_FIELD_MAX_LENGTH',
            'code.required' => 'CODE_FIELD_REQUIRED',
            'code.string' => 'CODE_MUST_BE_STRING',
            'description.required' => 'DESCRIPTION_FIELD_REQUIRED',
            'description.string' => 'DESCRIPTION_MUST_BE_STRING',
            'resources.array' => 'RESOURCES_MUST_BE_ARRAY',
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

            // User Profile validation messages  
            'user_id.required' => 'USER_ID_FIELD_REQUIRED',
            'user_id.integer' => 'USER_ID_MUST_BE_INTEGER',
            'display_name.required' => 'DISPLAY_NAME_FIELD_REQUIRED',
            'display_name.string' => 'DISPLAY_NAME_MUST_BE_STRING',
            'display_name.max' => 'DISPLAY_NAME_FIELD_MAX_LENGTH',
            'location.string' => 'LOCATION_MUST_BE_STRING',
            'location.max' => 'LOCATION_FIELD_MAX_LENGTH',
            'skills.array' => 'SKILLS_MUST_BE_ARRAY',
            'biography.string' => 'BIOGRAPHY_MUST_BE_STRING',
            'social_links.array' => 'SOCIAL_LINKS_MUST_BE_ARRAY',
            'website.string' => 'WEBSITE_MUST_BE_STRING',
            'website.max' => 'WEBSITE_FIELD_MAX_LENGTH',
            'avatar_path.string' => 'AVATAR_PATH_MUST_BE_STRING',
            'avatar_path.max' => 'AVATAR_PATH_FIELD_MAX_LENGTH',
            'is_public.required' => 'IS_PUBLIC_FIELD_REQUIRED',
            'is_public.boolean' => 'IS_PUBLIC_MUST_BE_BOOLEAN'

        ];
    }
}
