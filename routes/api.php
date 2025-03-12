<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\AuthController;

use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\Api\PostApiController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\API\UserProfileController;
use App\Http\Controllers\Api\UserReportController;

Route::middleware('throttle:api')->group(function () {


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
    //    "email": "john.doe@example.com",
    //    "created_at": "2025-03-12T10:15:00Z",
    //    "updated_at": "2025-03-12T10:15:00Z"
    //  }
    // - Returns 422 Validation Error for invalid input
    // - Returns 500 Server Error for unexpected issues
    Route::post('/register', [RegisterController::class, 'register']);

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
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('users', UserApiController::class);
    });


    //! Route for posts

    // Public routes - no authentication required
    // GET /api/posts - Get all posts
    // - Public users: Only see published posts
    // - Authenticated non-admin users: See published posts and their own drafts/archived posts
    // - Admins: See all posts
    //
    // - Select specific fields: GET /api/posts?select=id,title,language (supports: id, user_id, title, code, description, resources, language, category, tags, status, favorite_count, reports_count, created_at, updated_at)
    // - Sort options: GET /api/posts?sort=created_at (supports: id, user_id, title, language, category, tags, status, favorite_count, created_at, updated_at)
    // - Filter options: GET /api/posts?filter[language]=php (supports: title, user_id, language, category, tags, status, created_at, updated_at)
    // - Pagination: GET /api/posts?page=1&per_page=10
    //
    // GET /api/posts/{post} - Get a specific post
    // - Access restrictions follow the same rules as for listing (published posts visible to all)
    // - Select specific fields: GET /api/posts/{post}?select=id,title,code
    Route::get('/posts', [PostApiController::class, 'index']);
    Route::get('/posts/{post}', [PostApiController::class, 'show']);

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
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('posts', PostApiController::class)->except(['index', 'show']);
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
    // POST /api/posts/{post}/favorites - Add a post to favorites
    // - Authorization: Any authenticated user can add posts to their favorites
    // - No request body needed, post ID is taken from the URL
    // - Returns 201 Created on success, or 200 OK if already in favorites
    //
    // DELETE /api/posts/{post}/favorites - Remove a post from favorites
    // - Authorization: Users can only delete their own favorites (enforced by Policy)
    // - No request body needed, post ID is taken from the URL
    // - Returns 200 OK on success
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/posts/{post}/favorites', [FavoriteController::class, 'addFavorite']);
        Route::delete('/posts/{post}/favorites', [FavoriteController::class, 'removeFavorite']);
        Route::get('/user/favorites', [FavoriteController::class, 'getFavorites']);
    });

    //! Route for user profiles
    // Route to get, update user profiles - you need to be authenticated protected by sanctum and Policies
    // 
    // GET /api/user_profiles - Get all user profiles
    // - For admins: Returns all profiles
    // - For regular users: Returns only public profiles and the user's own profile
    // 
    // - Select specific fields: GET /api/user_profiles?select=id,user_id,display_name (supports: id, user_id, display_name, location, skills, biography, social_links, website, avatar_path, is_public, created_at, updated_at)
    // - Sort options: GET /api/user_profiles?sort=display_name (supports: id, user_id, display_name, location, created_at, updated_at, is_public)
    // - Filter options: GET /api/user_profiles?filter[display_name]=john (supports: user_id, display_name, location, skills, is_public)
    // - Pagination: GET /api/user_profiles?page=1&per_page=10
    //
    // GET /api/user_profiles/{id} - Get a specific user profile
    // - Authorization: Users can only view public profiles or their own profile, admins can view any profile
    // - Select fields: GET /api/user_profiles/{id}?select=id,display_name,location
    //
    // PUT/PATCH /api/user_profiles/{id} - Update a user profile
    // - Authorization: Users can only update their own profile, admins can update any profile
    // - Request body:
    //  {
    //    "display_name": "NewUsername",         // required, unique
    //    "location": "Berlin, Germany",         // optional
    //    "skills": ["PHP", "JavaScript"],       // optional, array
    //    "biography": "Full-stack developer",   // optional
    //    "social_links": {                      // optional, object
    //      "github": "https://github.com/user",
    //      "twitter": "https://twitter.com/user"
    //    },
    //    "website": "https://example.com",      // optional
    //    "avatar_path": "/storage/avatars/1.jpg", // optional
    //    "is_public": true                      // required, boolean
    //  }
    //
    // POST /api/user_profiles - Create a user profile (disabled, returns 403)
    // DELETE /api/user_profiles/{id} - Delete a user profile (disabled, returns 405)    
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('user_profiles', UserProfileController::class);
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
    // - Authorization: Any authenticated user can report posts or other users (with restrictions)
    // - Restrictions: Cannot report yourself or your own posts
    // - Request body:
    //  {
    //    "reportable_type": "post",      // required (post|user)
    //    "reportable_id": 5,             // required
    //    "reason": "Dieser Post enthält unangemessenen Inhalt"  // optional
    //  }
    // - Returns 201 Created on success, 409 Conflict if already reported
    //
    // DELETE /api/reports - Remove a report
    // - Authorization: Users can only delete their own reports (enforced by Policy)
    // - Request body:
    //  {
    //    "reportable_type": "post",    // required (post|user)
    //    "reportable_id": 5           // required
    //  }
    // - Returns 200 OK on success
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/reports', [UserReportController::class, 'addReport']);
        Route::delete('/reports', [UserReportController::class, 'removeReport']);
        Route::get('/reports', [UserReportController::class, 'getReports']);
    });
});
