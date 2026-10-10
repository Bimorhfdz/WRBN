<?php

use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ContactPersonController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\ServiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API
|--------------------------------------------------------------------------
|
| Registered under the "web" group so session cookies work, with api/* exempt
| from CSRF (the legacy frontend sends plain JSON; protection comes from the
| SameSite=Lax cookie plus same-origin requests).
|
*/

Route::prefix('api')->middleware('throttle:api')->group(function () {
    Route::get('/health', HealthController::class);

    Route::get('/services', [ServiceController::class, 'index']);
    Route::get('/contact-person', [ContactPersonController::class, 'show']);
    Route::post('/contact', [ContactController::class, 'store']);
    Route::get('/reviews', [ReviewController::class, 'index']);
    Route::post('/reviews', [ReviewController::class, 'store'])->middleware('auth.user');

    Route::prefix('auth')->middleware('throttle:auth-api')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me'])->middleware('auth.user');
    });

    Route::middleware('auth.user')->group(function () {
        Route::get('/client/dashboard', [ClientController::class, 'dashboard']);
        Route::get('/client/profile', [ClientController::class, 'profile']);
        Route::put('/client/profile', [ClientController::class, 'updateProfile']);

        Route::post('/payments', [PaymentController::class, 'store']);
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::get('/payments/{id}', [PaymentController::class, 'show']);
        Route::post('/payments/{id}/dummy-pay', [PaymentController::class, 'dummyPay']);

        Route::post('/consultations', [ConsultationController::class, 'store']);
        Route::get('/consultations', [ConsultationController::class, 'index']);
        Route::get('/consultations/{id}', [ConsultationController::class, 'show']);
        Route::patch('/consultations/{id}', [ConsultationController::class, 'update']);
        Route::post('/consultations/{id}/reschedule', [ConsultationController::class, 'reschedule']);
        Route::post('/consultations/{id}/upgrade', [ConsultationController::class, 'upgrade']);
        Route::post('/consultations/{id}/files', [FileController::class, 'store']);
        Route::get('/consultations/{id}/files', [FileController::class, 'index']);
    });

    Route::prefix('admin')->group(function () {
        Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:admin-login');
        Route::post('/logout', [AdminAuthController::class, 'logout']);

        Route::middleware('auth.admin')->group(function () {
            Route::get('/session', [AdminAuthController::class, 'session']);
            Route::get('/dashboard', [AdminController::class, 'dashboard']);
            Route::get('/users', [AdminController::class, 'users']);
            Route::delete('/users', [AdminController::class, 'deleteUsers'])->middleware('admin.role:superadmin');
            Route::get('/members', [AdminController::class, 'members']);
            Route::post('/members', [AdminController::class, 'addMembers'])->middleware('admin.role:superadmin');
            Route::delete('/members', [AdminController::class, 'removeMembers'])->middleware('admin.role:superadmin');
            Route::get('/projects', [AdminController::class, 'projects']);
            Route::get('/projects/{id}', [AdminController::class, 'project']);
            Route::patch('/projects/{id}', [AdminController::class, 'updateProject'])->middleware('admin.role:admin,superadmin');
            Route::post('/projects/{id}/progress', [AdminController::class, 'addProgress'])->middleware('admin.role:admin,superadmin');
            Route::patch('/projects/{id}/stage', [AdminController::class, 'updateStage'])->middleware('admin.role:admin,superadmin');
            Route::put('/projects/{id}/assignments', [AdminController::class, 'updateAssignments'])->middleware('admin.role:admin,superadmin');
            Route::get('/requests', [AdminController::class, 'requests']);
            Route::patch('/reschedules/{id}', [AdminController::class, 'resolveReschedule'])->middleware('admin.role:admin,superadmin');
            Route::patch('/upgrades/{id}', [AdminController::class, 'resolveUpgrade'])->middleware('admin.role:admin,superadmin');
            Route::get('/payments', [AdminController::class, 'payments']);
            Route::patch('/payments/{id}', [AdminController::class, 'updatePayment'])->middleware('admin.role:admin,superadmin');
            Route::get('/files/{id}/download', [AdminController::class, 'downloadFile']);
            Route::get('/messages', [AdminController::class, 'messages']);
        });
    });
});

/*
|--------------------------------------------------------------------------
| Pages
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return response()->file(public_path('index.html'));
});

// The admin SPA ships as a static file in public/, so /admin.html would be
// served by the web server without ever reaching Laravel. /admin and
// /admin/login are aliases so the panel has a normal URL; the SPA itself
// renders the login form whenever /api/admin/session returns 401.
Route::get('/admin', function () {
    return response()->file(public_path('admin.html'));
});

Route::get('/admin/login', function () {
    return response()->file(public_path('admin.html'));
});

// client.html lives outside public/ so it can be gated by the session.
Route::get('/client.html', function () {
    if (! Auth::guard('web')->check()) {
        return redirect('/');
    }

    return response()->file(resource_path('pages/client.html'));
});

Route::fallback(function (Request $request) {
    if ($request->is('api/*') || ! $request->isMethod('GET')) {
        abort(404, 'Endpoint tidak ditemukan.');
    }

    return response()->file(public_path('index.html'));
});
