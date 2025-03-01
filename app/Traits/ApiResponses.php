<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponses
{
    protected function successResponse($data, $message = null, $code = 200): JsonResponse {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'code' => $code,
            'data' => $data            
        ], $code);
    }

    protected function errorResponse($message, $errors = [], $code): JsonResponse {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'code' => $code,
            'errors' => $errors           
        ], $code);
    }

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
            'status.in' => 'STATUS_INVALID_OPTION'
        ];
    }
}
