<?php

use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleUpgradeRequestController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// ** Courses
Route::get('/courses/{courseId}', [CourseController::class, 'showCourseDetails']);
Route::put('/courses/{id}/update-status', [CourseController::class, 'updateStatus']);
Route::get('/course/{courseId}/section/{sectionId}', [CourseController::class, 'showSectionChapters']);
Route::get('/course/{courseId}/section/{sectionId}/chapter/{chapterId}', [CourseController::class, 'showChapterDetails']);
Route::get('/courses/get-update/{courseId}', [CourseController::class, 'showFullCourseDetails']);
Route::put('/courses/update/{courseId}', [CourseController::class, 'update']);
Route::post('courses', [CourseController::class, 'store']);
Route::post('course-access/add', [CourseController::class, 'addAccess']);
Route::get('/admin/get-course-review', [CourseController::class, 'getCoursesForReview']);
Route::get('/courses/creator/{creatorId}', [CourseController::class, 'getCourseByCreatorId']);
Route::get('/purchased-courses/{userId}', [CourseController::class, 'getPurchasedCoursesByUserId']);
Route::get('/purchased-courses/{userId}', [CourseController::class, 'getPurchasedCoursesByUserId']);
Route::get('/admin/courses', [CourseController::class, 'getAllCoursesForAdmin']);
Route::delete('/delete-course/{id}', [CourseController::class, 'destroyCourse']);

// ** User
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('logout', [AuthController::class, 'logout']);
Route::get('/users', [UserController::class, 'get']);
Route::delete('users/delete/{id}', [UserController::class, 'delete']);
Route::put('/users/update/{id}', [UserController::class, 'update']);
Route::post('refresh-token', [AuthController::class, 'refreshToken']);
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetCode']);
Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword']);
Route::post('/verify-token', [ForgotPasswordController::class, 'verifyToken']);
Route::put('/profile/update/{id}', [ProfileController::class, 'updateUser']);
Route::post('/upload', [FileUploadController::class, 'upload']);
Route::prefix('requests')->group(function () {
    Route::post('/', [RoleUpgradeRequestController::class, 'sendRequest'])
        ->middleware('auth:api');

    Route::get('/role-upgrade-requests', [RoleUpgradeRequestController::class, 'getRequests']);

    Route::put('{id}/approve', [RoleUpgradeRequestController::class, 'approveRequest'])
        ->middleware('auth:api');

    Route::post('{id}/reject', [RoleUpgradeRequestController::class, 'rejectRequest'])
        ->middleware('auth:api');
});
