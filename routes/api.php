<?php

use App\Http\Controllers\Api\ApiKeyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentApiController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\Api\PostApiController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\UserFollowerController;
use App\Http\Controllers\Api\UserLikeController;
use App\Http\Controllers\API\UserProfileController;
use App\Http\Controllers\Api\UserReportController;
use App\Http\Middleware\ValidateApiKey;

Route::middleware([ValidateApiKey::class, 'throttle:api'])->group(function () {

    //! Route for registration
    // Route to register a new user - no authentication required
    // POST /api/register - Create a new user account
    // - Request body:
    //  {
    //    "name": "John Doe",              // required, string, max 255 chars
    //    "email": "john.doe@example.com", // required, valid email, must be unique
    //    "password": "password123",       // required, min 8 chars
    //    "password_confirmation": "password123" // required, must match password
    //  }
    // - Returns user data on success with 201 Created:
    //  {
    //    "id": 1,
    //    "name": "John Doe",
    //    "display_name": "John Doe",
    //    "email": "john.doe@example.com",
    //    "created_at": "2025-03-12T10:15:00Z",
    //    "updated_at": "2025-03-12T10:15:00Z"
    //  }
    // - Returns 422 Validation Error for invalid input
    // - Returns 500 Server Error for unexpected issues
    Route::post('/register', [RegisterController::class, 'register']);

    //! Route for login
    // Public routes - no authentication required
    // POST /api/login - Login and get access token
    // - Request body:
    //  {
    //    "email": "user@example.com",     // required, valid email
    //    "password": "yourpassword",      // required
    //    "device_name": "My iPhone"       // required, name for the device/token
    //  }
    // - Returns access token on success with 200 OK
    // - Returns 401 Unauthorized on invalid credentials
    Route::post('/login', [AuthController::class, 'login']);

    //! Route for logout and tokens
    // Protected routes - authentication required
    // POST /api/logout - Logout and revoke current token
    // - Authorization: Any authenticated user can logout
    // - No request body needed
    // - Returns 200 OK on success
    //
    // GET /api/tokens - Get list of all active tokens for current user
    // - Authorization: Any authenticated user can view their tokens
    // - No request body needed
    // - Returns array of tokens with device names, creation dates and current status
    // - Format of returned data:
    //  [
    //    {
    //      "id": 1,
    //      "device_name": "My iPhone",
    //      "last_used": "2025-03-12T10:15:00Z",
    //      "created_at": "2025-03-10T08:30:00Z",
    //      "is_current": true
    //    }
    //  ]
    //
    // DELETE /api/tokens/{id} - Revoke a specific token
    // - Authorization: Users can only revoke their own tokens
    // - Path parameter: token ID (numeric)
    // - Cannot revoke the current token using this endpoint
    // - Returns 200 OK on success, 400 for invalid ID, 403 if trying to revoke current token, 404 if token not found
    //
    // DELETE /api/tokens - Revoke all tokens except current
    // - Authorization: Any authenticated user can revoke all their other tokens
    // - No request body needed
    // - Returns 200 OK on success, 400 if there are no other active tokens
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/tokens', [AuthController::class, 'getUserTokens']);
        Route::delete('/tokens/{id}', [AuthController::class, 'revokeToken']);
        Route::delete('/tokens', [AuthController::class, 'revokeAllTokens']);
    });


    //! Route for ban and unban users
    Route::middleware(['auth:sanctum', 'email-verified'])->group(function () {
        Route::get('/users/banned', [UserApiController::class, 'getUsersWithBanHistory']);
        Route::post('/users/{id}/ban', [UserApiController::class, 'banUser']);
        Route::post('/users/{id}/unban', [UserApiController::class, 'unbanUser']);
    });


    //! Route for users
    // Route to get, view, update and delete users - you need to be authenticated protected by sanctum and Policies
    // 
    // GET /api/users - Get all users
    // - Authorization: Only administrators can access this endpoint (enforced by Policy)
    // 
    // - Select specific fields: GET /api/users?select=id,name,email (supports: id, name, email, created_at, updated_at)
    // - Sort options: GET /api/users?sort=name (supports: id, name, email, created_at, updated_at)
    // - Filter options: GET /api/users?filter[name]=john (supports: name, email, created_at, updated_at)
    // - Pagination: GET /api/users?page=1&per_page=10
    //
    // GET /api/users/{id} - Get a specific user
    // - Authorization: Users can only view their own profile, admins can view any user
    // - Select fields: GET /api/users/{id}?select=id,name,email
    // - Returns 200 OK on success, 404 if user not found, 403 if unauthorized
    //
    // PUT/PATCH /api/users/{id} - Update a user
    // - Authorization: Users can only update their own profile, admins can update any user
    // - Request body:
    //  {
    //    "name": "John Doe",              // required, string
    //    "email": "john.doe@example.com", // required, string, unique email
    //    "password": "newpassword",       // required, min 8 chars
    //    "password_confirmation": "newpassword"  // required when password is present
    //  }
    // - Returns 200 OK on success, 404 if user not found, 422 for validation errors, 403 if unauthorized
    //
    // DELETE /api/users/{id} - Delete a user
    // - Authorization: Users can only delete their own profile, admins can delete any user
    // - No request body needed
    // - Returns 200 OK on success, 404 if user not found, 403 if unauthorized
    Route::middleware(['auth:sanctum', 'email-verified'])->group(function () {
        Route::apiResource('users', UserApiController::class);
    });


    //! Route for posts
    // Public routes - no authentication required
    // GET /api/posts - Get all posts
    // - Public users: Only see published posts
    // - Authenticated non-admin users: See published posts and their own drafts/archived posts
    // - Admins: See all posts
    //
    // - Headers for external content: Include the following headers to display external content for non-logged in users:
    //   'X-Show-External-Images: true' - Display external images
    //   'X-Show-External-Videos: true' - Display external videos
    //   X-Show-External-Resources: true' - Display external links/resources
    //   These headers are only required for anonymous users. Logged-in users use their profile settings instead.
    //
    // - Select specific fields: GET /api/posts?select=id,title,language (supports: id, user_id, title, code, description, resources, images, language, category, tags, status, favorite_count, reports_count, created_at, updated_at)
    // - Sort options: GET /api/posts?sort=created_at (supports: id, user_id, title, language, category, tags, status, favorite_count, created_at, updated_at)
    // - Filter options: GET /api/posts?filter[language]=php (supports: title, user_id, language, category, tags, status, created_at, updated_at)
    // - Pagination: GET /api/posts?page=1&per_page=10
    //
    // GET /api/posts/{post} - Get a specific post
    // - Access restrictions follow the same rules as for listing (published posts visible to all)
    // - Supports 'X-Show-External-Images', 'X-Show-External-Videos', 'X-Show-External-Resources' headers for anonymous users
    // - Select specific fields: GET /api/posts/{post}?select=id,title,code
    //
    // GET /api/posts/{user_id}/received-interactions - Get total interactions for a user's posts
    // - Returns the total count of likes or favorites that a user has received on all their posts
    // - Query parameters:
    // - type: required, string, either 'likes_count' or 'favorite_count'
    // - Example: GET /api/posts/1/received-interactions?type=favorite_count
    // - Returns: 
    // - {
    // -    "status": "success",
    // -    "message": "Total favorite_count for user with ID 1",
    // -    "code": 200,
    // -    "count": 1,
    // -    "data": 5
    // - }
    Route::get('/posts', [PostApiController::class, 'index']);
    Route::get('/posts/{post}', [PostApiController::class, 'show']);
    Route::get('/posts/{user_id}/received-interactions', [PostApiController::class, 'getUserPostsInteractions']);


    // Protected routes - authentication required
    // POST /api/posts - Create a new post
    // - Authorization: Any authenticated user can create posts
    // - Request body:
    //  {
    //    "title": "My Code Snippet",       // required
    //    "code": "console.log('Hello')",   // required
    //    "description": "A simple example", // required
    //    "resources": ["https://example.com"], // optional, array
    //    "language": "javascript",         // required
    //    "category": "frontend",           // required
    //    "tags": ["beginner", "tutorial"], // required, array
    //    "status": "published"             // required (draft|published|archived)
    //  }
    // - Returns 201 Created on success
    //
    // PUT/PATCH /api/posts/{post} - Update a post
    // - Authorization: Users can only update their own posts, admins can update any post
    // - Request body: Same format as POST
    // - Returns 200 OK on success
    //
    // DELETE /api/posts/{post} - Delete a post
    // - Authorization: Users can only delete their own posts, admins can delete any post
    // - No request body needed
    // - Returns 200 OK on success
    Route::middleware(['auth:sanctum', 'email-verified'])->group(function () {
        Route::apiResource('posts', PostApiController::class)->except(['index', 'show']);
    });


    //! Routes for user profiles
    // Route to get, update user profiles - you need to be authenticated protected by sanctum and Policies
    // 
    // GET /api/user-profiles - Get all user profiles
    // - For admins: Returns all profiles
    // - For regular users: Returns only public profiles and the user's own profile
    // 
    // - Select specific fields: GET /api/user-profiles?select=id,user_id,display_name
    // - Sort options: GET /api/user-profiles?sort=display_name
    // - Filter options: GET /api/user-profiles?filter[display_name]=john
    // - Pagination: GET /api/user-profiles?page=1&per_page=10
    //
    // GET /api/user-profiles/{id} - Get a specific user profile
    // - Authorization: Users can only view public profiles or their own profile, admins can view any profile
    //
    // PUT/PATCH /api/user-profiles/{id} - Update a user profile
    // - Authorization: Users can only update their own profile, admins can update any profile
    // Request body für das user-profiles Update:
    //  {
    //    "display_name": "NewUsername",         // string, unique
    //    "public_email": "contact@example.com", // email address 
    //    "location": "Berlin, Germany",         // string
    //    "skills": ["PHP", "JavaScript"],       // array of skills
    //    "biography": "Full-stack developer",   // string
    //    "contact_channels": {                  // object with allowed channels
    //      "discord": "user#1234"
    //    },
    //    "social_links": {                      // object with allowed social platforms
    //      "github": "https://github.com/",
    //      "linkedin": "https://linkedin.com/in/"
    //    },
    //    "website": "https://example.com",      // string, URL
    //    "avatar_path": "/storage/avatars/1.jpg", // string
    //    "is_public": true,                     // boolean
    //    "auto_load_external_images": true,     // boolean (permanent setting for images)
    //    "auto_load_external_videos": true,     // boolean (permanent setting for videos)
    //    "auto_load_external_resources": true,  // boolean (permanent setting for resources)
    //    "external_images_temp_until": "2025-04-03T20:00:00Z", // date (temporary access for images)
    //    "external_videos_temp_until": "2025-04-03T20:00:00Z", // date (temporary access for videos)
    //    "external_resources_temp_until": "2025-04-03T20:00:00Z" // date (temporary access for resources)
    //  }
    //
    // POST /api/user-profiles/{id}/enable-temporary-externals - Convenience endpoint for temporary external content settings
    // - Authorization: Users can only modify their own profile settings
    // - Request body:
    //  {
    //    "type": "images",  // required, string, one of: images, videos, resources
    //    "hours": 24        // required, integer between 0-72 (0 = disable temporary access)
    //  }
    // - Response: User profile with confirmation message
    Route::middleware(['auth:sanctum', 'email-verified'])->group(function () {
        Route::apiResource('user-profiles', UserProfileController::class);
        Route::post('user-profiles/{id}/enable-temporary-externals', [UserProfileController::class, 'enableTemporaryExternals']);
    });


    //! Route for API keys
    // Protected routes - authentication required and admin only
    // 
    // POST /api/api-keys - Generate a new API key
    // - Authorization: Only administrators can generate API keys
    // - Request body:
    //  {
    //    "name": "My API Key"  // required, string, max 255 chars
    //  }
    // - Returns:
    //  {
    //    "api_key": "generated_key_string",
    //    "name": "My API Key",
    //    "created_at": "2025-03-13T20:55:56Z"
    //  }
    //
    // GET /api/api-keys - List all API keys
    // - Authorization: Only administrators can view API keys
    // - Returns array of API keys (key field is not included):
    //  [
    //    {
    //      "id": 1,
    //      "name": "My API Key",
    //      "active": true,
    //      "created_at": "2025-03-13T20:55:56Z",
    //      "last_used_at": "2025-03-13T21:00:00Z"
    //    }
    //  ]
    //
    // PATCH /api/api-keys/{apiKey}/toggle - Toggle API key status
    // - Authorization: Only administrators can toggle API key status
    // - No request body needed
    // - Returns updated API key status
    //
    // DELETE /api/api-keys/{apiKey} - Delete an API key
    // - Authorization: Only administrators can delete API keys
    // - No request body needed
    // - Returns success message
    Route::middleware(['auth:sanctum', 'email-verified'])->group(function () {
        Route::post('/api-keys', [ApiKeyController::class, 'generate']);
        Route::get('/api-keys', [ApiKeyController::class, 'index']);
        Route::patch('/api-keys/{apiKey}/toggle', [ApiKeyController::class, 'toggleStatus']);
        Route::delete('/api-keys/{apiKey}', [ApiKeyController::class, 'destroy']);
    });


    //! Route for comments
    // Route to get, view, update and delete comments - you need to be authenticated protected by sanctum and Policies
    //
    // GET /api/comments - Get all comments
    // - Authorization: Only administrators can access this endpoint (enforced by Policy)
    //
    // - Select specific fields: GET /api/comments?select=id,content (supports: id, post_id, user_id, parent_id, content, is_deleted, is_edited, edited_at, likes_count, reports_count, created_at, updated_at)
    // - Sort options: GET /api/comments?sort=created_at (supports: id, post_id, user_id, content, is_deleted, is_edited, edited_at, likes_count, reports_count, created_at, updated_at)
    // - Filter options: GET /api/comments?filter[content]=example (supports: post_id, user_id, parent_id, content, is_deleted, is_edited, edited_at, likes_count, reports_count, created_at, updated_at)
    // - Pagination: GET /api/comments?page=1&per_page=10
    //
    // GET /api/comments/{comment} - Get a specific comment
    // - Authorization: Users can only view their own comments, admins can view any comment
    // - Select fields: GET /api/comments/{comment}?select=id,content
    //
    // POST /api/comments - Create a new comment
    // - Authorization: Any authenticated user can create comments
    // - Request body:
    //  {
    //    "post_id": 1,                    // required
    //    "parent_id": 5,                  // optional
    //    "content": "This is a comment"   // required
    //  }
    // - Returns 201 Created on success
    //
    // PUT/PATCH /api/comments/{comment} - Update a comment
    // - Authorization: Users can only update their own comments, admins can update any comment
    // - Request body:
    //  {
    //    "content": "This is an updated comment"  // required
    //  }
    // - Returns 200 OK on success
    //
    // DELETE /api/comments/{comment} - Delete a comment
    // - Authorization: Users can only delete their own comments, admins can delete any comment
    // - No request body needed
    // - Returns 200 OK on success
    //
    // PATCH /api/comments/{id}/toggle-status - deleteComment
    // - Authorization: Users can only delete their own comments, admins can delete any comment
    // - No request body needed
    // - Returns updated comment status
    Route::middleware(['auth:sanctum', 'email-verified'])->group(function () {
        Route::apiResource('comments', CommentApiController::class);
        Route::patch('comments/{id}/deleteComment', [CommentApiController::class, 'deleteComment']);
    });


    //! Route for favorites
    // Route to add, remove a favorite and get all favorites you need to be authenticated 
    // 
    // GET /api/user/favorites - Get all favorites of the authenticated user
    // - Authorization: Users can only access their own favorites
    //
    // - Minimal data: GET /api/user/favorites
    // - With post data: GET /api/user/favorites?include=post
    // 
    // - Select specific fields: GET /api/user/favorites?select=id,user_id,title (supports: id, user_id, title, code, description, resources, language, category, tags, status, favorite_count, reports_count, created_at, updated_at)
    // - Sort options: GET /api/user/favorites?sort=created_at (supports: id, user_id, title, language, category, tags, status, favorite_count, created_at, updated_at)
    // - Filter options: GET /api/user/favorites?filter[title]=example (supports: title, user_id, language, category, tags, status, created_at, updated_at)
    // - Pagination: GET /api/user/favorites?page=1&per_page=10
    //
    // GET /api/user/favorites/posts - Get posts favorited by the authenticated user
    // - Authorization: Users can only access their own favorite posts
    // - Returns the actual post objects rather than favorite relationship objects
    // 
    // - Select specific fields: GET /api/user/favorites/posts?select=id,title,language (supports: id, user_id, title, code, description, resources, language, category, tags, status, favorite_count, reports_count, created_at, updated_at)
    // - Sort options: GET /api/user/favorites/posts?sort=created_at (supports: id, user_id, title, language, category, tags, status, favorite_count, created_at, updated_at)
    // - Filter options: GET /api/user/favorites/posts?filter[language]=php (supports: title, user_id, language, category, tags, status, created_at, updated_at)
    // - Pagination: GET /api/user/favorites/posts?page=1&per_page=10
    //
    // POST /api/posts/{post}/favorites - Add a post to favorites
    // - Authorization: Any authenticated user can add posts to their favorites
    // - No request body needed, post ID is taken from the URL
    // - Returns 201 Created on success, or 200 OK if already in favorites
    //
    // DELETE /api/posts/{post}/favorites - Remove a post from favorites
    // - Authorization: Users can only delete their own favorites (enforced by Policy)
    // - No request body needed, post ID is taken from the URL
    // - Returns 200 OK on success
    // 
    Route::middleware(['auth:sanctum', 'email-verified'])->group(function () {
        Route::get('/user/favorites', [FavoriteController::class, 'index']);
        Route::post('/posts/{post}/favorites', [FavoriteController::class, 'store']);
        Route::delete('/posts/{post}/favorites', [FavoriteController::class, 'destroy']);

        Route::get('/user/favorites/posts', [FavoriteController::class, 'getFavoritePosts']);
    });


    //! Route for reports
    // Route to add, remove a report and get all reports you need to be authenticated
    // 
    // GET /api/reports - Get all reports
    // - Authorization: Only administrators can access this endpoint (enforced by Policy)
    // 
    // - Minimal data: GET /api/reports
    // - With user data: GET /api/reports?include=user
    // - With reportable data: GET /api/reports?include=reportable
    // - With all data: GET /api/reports?include=user,reportable
    //
    // - Select specific fields: GET /api/reports?select=id,user_id,reason (supports: id, user_id, reportable_id, reportable_type, type, reason, created_at, updated_at)
    // - Sort options: GET /api/reports?sort=created_at (supports: id, user_id, reportable_id, reportable_type, type, created_at, updated_at)
    // - Filter options: GET /api/reports?filter[type]=post (supports: user_id, reportable_id, reportable_type, type, created_at, updated_at)
    // - Pagination: GET /api/reports?page=1&per_page=10
    //
    // POST /api/reports - Add a new report
    // - Authorization: Any authenticated user can report posts, users or comments (with restrictions)
    // - Restrictions: Cannot report yourself, your own posts, or your own comments
    // - Request body:
    //  {
    //    "reportable_type": "post",      // required (post|user|comment)
    //    "reportable_id": 5,             // required
    //    "reason": "Dieser Post enthält unangemessenen Inhalt"  // optional
    //  }
    // - Returns 201 Created on success with report data
    // - Returns 403 Forbidden if trying to report own content
    // - Returns 409 Conflict if already reported
    // - Returns 404 Not Found if entity doesn't exist
    //
    // DELETE /api/reports - Remove a report
    // - Authorization: Users can only delete their own report, admins can delete any report
    // - Request body:
    //  {
    //    "reportable_type": "post",    // required (post|user|comment)
    //    "reportable_id": 5,           // required
    //    "user_id": 1                  // optional, admin only - specify which user's report to remove
    //  }
    // - Returns 200 OK on success
    // - Returns 404 Not Found if report doesn't exist
    Route::middleware(['auth:sanctum', 'email-verified'])->group(function () {
        Route::apiResource('reports', UserReportController::class)->only(['index', 'store']);
        Route::delete('/reports', [UserReportController::class, 'destroy']);
    });


    //! Route for likes
    // Route to add, remove and get likes - you need to be authenticated protected by sanctum and Policies
    // 
    // GET /api/likes - Get all likes
    // - Authorization: Only administrators can access this endpoint (enforced by Policy)
    // 
    // - Minimal data: GET /api/likes
    // - With user data: GET /api/likes?include=user
    // - With likeable data: GET /api/likes?include=likeable
    // - With all data: GET /api/likes?include=user,likeable
    //
    // - Select specific fields: GET /api/likes?select=id,user_id (supports: id, user_id, likeable_id, likeable_type, type, created_at, updated_at)
    // - Sort options: GET /api/likes?sort=created_at (supports: id, user_id, likeable_id, likeable_type, type, created_at, updated_at)
    // - Filter options: GET /api/likes?filter[type]=post (supports: user_id, likeable_id, likeable_type, type, created_at, updated_at)
    // - Pagination: GET /api/likes?page=1&per_page=10
    //
    // POST /api/likes - Add a new like
    // - Authorization: Any authenticated user can like posts or comments (with restrictions)
    // - Restrictions: Cannot like your own posts or comments
    // - Request body:
    //  {
    //    "likeable_type": "post",      // required (post|comment)
    //    "likeable_id": 5              // required
    //  }
    // - Returns 201 Created on success, 403 Forbidden if trying to like own content or already liked
    //
    // DELETE /api/likes - Remove a like
    // - Authorization: Users can only delete their own likes (enforced by Policy)
    // - Request body:
    //  {
    //    "likeable_type": "post",    // required (post|comment)
    //    "likeable_id": 5            // required
    //  }
    // - Returns 200 OK on success, 404 Not Found if like doesn't exist
    //
    // GET /api/user-likes/posts - Get posts liked by the authenticated user
    // - Authorization: Users can only access their own liked posts
    // - Returns the actual post objects that were liked by the user
    // 
    // - Select specific fields: GET /api/user-likes/posts?select=id,title,user_id (supports: all post fields)
    // - Sort options: GET /api/user-likes/posts?sort=created_at (supports: all post fields)
    // - Filter options: GET /api/user-likes/posts?filter[title]=example (supports: all post fields)
    // - Pagination: GET /api/user-likes/posts?page=1&per_page=10
    //
    // GET /api/user-likes/comments - Get comments liked by the authenticated user
    // - Authorization: Users can only access their own liked comments
    // - Returns the actual comment objects that were liked by the user
    // 
    // - Select specific fields: GET /api/user-likes/comments?select=id,content,user_id (supports: all comment fields)
    // - Sort options: GET /api/user-likes/comments?sort=created_at (supports: all comment fields)
    // - Filter options: GET /api/user-likes/comments?filter[content]=example (supports: all comment fields)
    // - Pagination: GET /api/user-likes/comments?page=1&per_page=10
    Route::middleware(['auth:sanctum', 'email-verified'])->group(function () {
        Route::apiResource('likes', UserLikeController::class)->only(['index', 'store']);
        Route::delete('/likes', [UserLikeController::class, 'destroy']);

        Route::get('/user-likes/posts', [UserLikeController::class, 'getLikedPosts']);
        Route::get('/user-likes/comments', [UserLikeController::class, 'getLikedComments']);
    });

    //! Route for followers
    // Route to get followers, following users, follow and unfollow - you need to be authenticated protected by sanctum
    // 
    // GET /api/followers - Get all followers for the authenticated user
    // - Authorization: Users can only access their own followers
    // - Returns user objects with is_followed flag indicating if you follow them back
    // 
    // - Select specific fields: GET /api/followers?select=id,user_id,follower_id (supports: id, user_id, follower_id, created_at, updated_at)
    // - Sort options: GET /api/followers?sort=created_at (supports: id, user_id, follower_id, created_at, updated_at)
    // - Filter options: GET /api/followers?filter[created_at]=2025-04-01 (supports: user_id, follower_id, created_at, updated_at)
    // - Pagination: GET /api/followers?page=1&per_page=10
    //
    // GET /api/following - Get all users the authenticated user is following
    // - Authorization: Users can only access their own following list
    // - Returns user objects with is_following flag indicating if they follow you back
    // 
    // - Select specific fields: GET /api/following?select=id,user_id,follower_id (supports: id, user_id, follower_id, created_at, updated_at)
    // - Sort options: GET /api/following?sort=created_at (supports: id, user_id, follower_id, created_at, updated_at)
    // - Filter options: GET /api/following?filter[created_at]=2025-04-01 (supports: user_id, follower_id, created_at, updated_at)
    // - Pagination: GET /api/following?page=1&per_page=10
    //
    // POST /api/follow/{userId} - Follow a user
    // - Authorization: Any authenticated user can follow other users (cannot follow self)
    // - No request body needed, user ID is taken from the URL
    // - Returns 201 Created on success, 200 OK if already following
    // - Returns 400 Bad Request if trying to follow yourself
    // - Returns 404 Not Found if user doesn't exist
    //
    // DELETE /api/unfollow/{userId} - Unfollow a user
    // - Authorization: Users can only unfollow users they are currently following
    // - No request body needed, user ID is taken from the URL
    // - Returns 200 OK on success
    // - Returns 404 Not Found if not following or user doesn't exist
    Route::middleware(['auth:sanctum', 'email-verified'])->group(function () {
        Route::get('followers', [UserFollowerController::class, 'getFollowers']);
        Route::get('following', [UserFollowerController::class, 'getFollowing']);
        Route::post('follow/{userId}', [UserFollowerController::class, 'follow']);
        Route::delete('unfollow/{userId}', [UserFollowerController::class, 'unfollow']);
    });
});
