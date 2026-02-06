<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ActivateController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\FloorPlanController;
use App\Http\Controllers\Api\AnnotationController;
use App\Http\Controllers\Api\RecycleBinController;
use App\Http\Controllers\Api\AttributeImageController;
use App\Http\Controllers\Api\AttributeObservationController;
use App\Http\Controllers\Api\AttributeHeldByController;
use App\Http\Controllers\Api\AttributeProgressController;
use App\Http\Controllers\Api\AttributeProgramController;
use App\Http\Controllers\Api\AttributeAssigneeController;
use App\Http\Controllers\Api\AttributeAccountableController;
use App\Http\Controllers\Api\ProgramProgressController;
use App\Http\Controllers\Api\ServiceAttributeCopyController;
use App\Http\Controllers\Api\SiteTeamController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\InviteController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\MenuController;


// For API (React frontend)
Route::post('/reset-password', [ResetPasswordController::class, 'reset']);



Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'message' => 'Katchap API is running'
    ]);
});
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/email/verify', [RegisterController::class, 'verifyEmail']);
Route::middleware('auth:sanctum')->post('/invite', [InviteController::class, 'invite']);
Route::middleware('auth:sanctum')->get('/invite/roles', [InviteController::class, 'getAvailableRoles']);
// Allow cancelling / deleting pending invitations
Route::middleware('auth:sanctum')->delete('/invitations/{invitation}', [InviteController::class, 'destroy']);

// Super Admin Company Management Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('companies', CompanyController::class);
    Route::post('companies/{company}/activate', [CompanyController::class, 'activate']);
    Route::post('companies/{company}/suspend', [CompanyController::class, 'suspend']);
    Route::get('companies-analytics', [CompanyController::class, 'analytics']);
    Route::get('all-users', [CompanyController::class, 'getAllUsers']);
});

// Super Admin User Management Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('users', UserController::class);
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);
    Route::post('users/{user}/toggle-status', [UserController::class, 'toggleStatus']);
});

// Super Admin Role Management Routes
Route::middleware(['auth:sanctum', 'no-cache'])->group(function () {
    // Place diagnostic route before resource to avoid route conflicts (roles/{role} capturing 'diagnose')
    Route::get('roles/diagnose', [RoleController::class, 'diagnose']);
    Route::apiResource('roles', RoleController::class);
    Route::get('permissions', [RoleController::class, 'getPermissions']);
});
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    $user = $request->user();

    // Handle case when authenticated user is null
    if (!$user) {
        return response()->json([
            'status' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    $user->load('role.permissions');
    return response()->json([
        'id' => $user->id,
        'email' => $user->email,
        'role' => $user->role ? $user->role->name : null,
        'permissions' => $user->role ? $user->role->permissions->pluck('name')->toArray() : [],
    ]);
});
Route::middleware('auth:sanctum')->post('/admin/create-manager', [AdminController::class, 'createManager']);
Route::middleware('auth:sanctum')->get('/admin/dashboard', [AdminController::class, 'dashboard']);
Route::middleware('auth:sanctum')->post('/admin/update-settings', [AdminController::class, 'updateSettings']);
Route::middleware('auth:sanctum')->post('/admin/promote-email', [AdminController::class, 'promoteEmail']);
Route::post('/activate', [ActivateController::class, 'activate']);
Route::post('/login', [LoginController::class, 'login']);
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink']);
Route::post('/reset-password', [ResetPasswordController::class, 'reset']);

// Temporary: Move projects outside auth for testing
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::put('/projects/{project}', [ProjectController::class, 'update']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
});

Route::middleware('auth:sanctum')->group(function () {
       Route::get('/profile', [LoginController::class, 'profile']);
       Route::get('/roles', [LoginController::class, 'getAllRoles']);
       Route::get('/menus', [MenuController::class, 'getMenus']);
    // FloorPlan CRUD under projects
    Route::prefix('projects/{project}')->group(function () {
        Route::get('/floorplans', [FloorPlanController::class, 'index']);       // List floorplans of a project
        Route::post('/floorplans', [FloorPlanController::class, 'store']);      // Upload a new floorplan
        Route::put('/floorplans/{floorplan}', [FloorPlanController::class, 'update']); // Update floorplan (title/file)
        Route::delete('/floorplans/{floorplan}', [FloorPlanController::class, 'destroy']); // Delete floorplan
      // Recycle bin
    Route::get('floorplans/recycle-bin', [FloorPlanController::class, 'recycleBin']);
    Route::post('floorplans/{id}/restore', [FloorPlanController::class, 'restore']); // Restore
    Route::delete('floorplans/{id}/force-delete', [FloorPlanController::class, 'forceDelete']); // Permanent delete        // Site Teams
        Route::get('/site-teams', [SiteTeamController::class, 'index']);
        Route::post('/site-teams', [SiteTeamController::class, 'store']);
        Route::put('/site-teams/{userId}', [SiteTeamController::class, 'update']);
        Route::delete('/site-teams/{userId}', [SiteTeamController::class, 'destroy']);  // Load all annotations (remains SAME)
        Route::get('/floorplans/{floorplan}/annotations', [AnnotationController::class, 'load']);

        /**
         * SPACES
         */
        Route::post('/floorplans/{floorplan}/spaces', [AnnotationController::class, 'createSpace']);
        Route::put('/floorplans/{floorplan}/spaces/{space}', [AnnotationController::class, 'updateSpace']);
        Route::delete('/floorplans/{floorplan}/spaces/{space}', [AnnotationController::class, 'deleteSpace']);

        /**
         * SERVICES
         */
        Route::post('/floorplans/{floorplan}/spaces/{space}/services', [AnnotationController::class, 'createService']);
        Route::put('/floorplans/{floorplan}/services/{service}', [AnnotationController::class, 'updateService']);
        Route::delete('/floorplans/{floorplan}/services/{service}', [AnnotationController::class, 'deleteService']);

        /**
         * ATTRIBUTES
         */
        Route::post('/floorplans/{floorplan}/services/{service}/attributes', [AnnotationController::class, 'createAttribute']);
        Route::put('/floorplans/{floorplan}/attributes/{attribute}', [AnnotationController::class, 'updateAttribute']);
        Route::delete('/floorplans/{floorplan}/attributes/{attribute}', [AnnotationController::class, 'deleteAttribute']);
// FloorPlan transformation settings
Route::post('/floorplans/{floorplan}/set-rotation', [FloorPlanController::class, 'updateRotation']);
Route::post('/floorplans/{floorplan}/set-origin', [FloorPlanController::class, 'updateOrigin']);
Route::post('/floorplans/{floorplan}/calibrate', [FloorPlanController::class, 'updateCalibration']);
Route::get('/floorplans/{floorplan}/settings', [FloorPlanController::class, 'getSettings']);
    });

Route::prefix('recycle-bin')->group(function () {

        // LIST ALL DELETED ITEMS
        Route::get('/', [RecycleBinController::class, 'deleted']);

        // -----------------------------------
        // SOFT DELETE (Move to Recycle Bin)
        // -----------------------------------
        Route::delete('/space/{id}', [RecycleBinController::class, 'deleteSpace']);
        Route::delete('/service/{id}', [RecycleBinController::class, 'deleteService']);
        Route::delete('/attribute/{id}', [RecycleBinController::class, 'deleteAttribute']);

        // -----------------------------------
        // RESTORE FROM RECYCLE BIN
        // -----------------------------------
        Route::post('/space/{id}/restore', [RecycleBinController::class, 'restoreSpace']);
        Route::post('/service/{id}/restore', [RecycleBinController::class, 'restoreService']);
        Route::post('/attribute/{id}/restore', [RecycleBinController::class, 'restoreAttribute']);

        // -----------------------------------
        // FORCE DELETE (Permanent Remove)
        // -----------------------------------
        Route::delete('/space/{id}/force', [RecycleBinController::class, 'forceDeleteSpace']);
        Route::delete('/service/{id}/force', [RecycleBinController::class, 'forceDeleteService']);
        Route::delete('/attribute/{id}/force', [RecycleBinController::class, 'forceDeleteAttribute']);
    });
});




Route::middleware('auth:sanctum')->group(function () {
    Route::post('/program-progress', [ProgramProgressController::class, 'store']);
    Route::get('/program-progress/{programId}/latest', [ProgramProgressController::class, 'latest']);
    // Get all progress updates for a program
    Route::get('/program-progress/{programId}/all', [ProgramProgressController::class, 'all']);
    Route::post('/profile/update', [LoginController::class, 'updateProfile']);

});

Route::middleware('auth:sanctum')->group(function () {
    // Attribute Images
    Route::prefix('projects/{project}/floorplans/{floorplan}')->group(function () {
        // Copy attributes from one service to another (same or different space)
Route::post(
    '/services/{targetService}/copy-attributes',
    [ServiceAttributeCopyController::class, 'copy']
);

        // List attribute images, with optional filtering by space/service/attribute
        Route::get('/attribute-images', [AttributeImageController::class, 'index']);

        // Store a new attribute image
        Route::post('/attribute-images', [AttributeImageController::class, 'store']);

        // Show a specific attribute image
        Route::get('/attribute-images/{image}', [AttributeImageController::class, 'show']);

        // Update metadata (title, description, coordinates)
        Route::put('/attribute-images/{image}', [AttributeImageController::class, 'update']);

        // Delete attribute image
        Route::delete('/attribute-images/{image}', [AttributeImageController::class, 'destroy']);
    });
});

Route::middleware('auth:sanctum')->group(function () {

    // Attribute Observations
    Route::prefix('projects/{project}/floorplans/{floorplan}')->group(function () {

        // List all observations (optional filters: space_id, service_id, attribute_id)
        Route::get('/attribute-observations', [AttributeObservationController::class, 'index']);

        // Store a new observation
        Route::post('/attribute-observations', [AttributeObservationController::class, 'store']);

        // Show a single observation
        Route::get('/attribute-observations/{observation}', [AttributeObservationController::class, 'show']);

        // Update an existing observation
        Route::put('/attribute-observations/{observation}', [AttributeObservationController::class, 'update']);

        // Delete an observation
Route::delete('/attribute-observations/{observation}', [AttributeObservationController::class, 'destroy']);

   // List all progress records (optional filters: space_id, service_id, attribute_id)
        Route::get('/attribute-progress', [AttributeProgressController::class, 'index']);

        // Store a new progress record
        Route::post('/attribute-progress', [AttributeProgressController::class, 'store']);

        // Show a single progress record
        Route::get('/attribute-progress/{progress}', [AttributeProgressController::class, 'show']);

        // Update an existing progress record
        Route::put('/attribute-progress/{progress}', [AttributeProgressController::class, 'update']);

        // Delete a progress record
        Route::delete('/attribute-progress/{progress}', [AttributeProgressController::class, 'destroy']);






         // List all progress records (optional filters: space_id, service_id, attribute_id)
        Route::get('/attribute-program', [AttributeProgramController::class, 'index']);

        // Store a new progress record
        Route::post('/attribute-program', [AttributeProgramController::class, 'store']);

        // Show a single progress record
        Route::get('/attribute-program/{program}', [AttributeProgramController::class, 'show']);

        // Update an existing progress record
       
Route::put('/attribute-program/{program}', [AttributeProgramController::class, 'update']);

        // Delete a progress record
        Route::delete('/attribute-program/{program}', [AttributeProgramController::class, 'destroy']);

                Route::get('/attribute-assignee', [AttributeAssigneeController::class, 'index']);

        // Store a new progress record
        Route::post('/attribute-assignee', [AttributeAssigneeController::class, 'store']);

        // Show a single progress record
        Route::get('/attribute-assignee/{assignee}', [AttributeAssigneeController::class, 'show']);

        // Update an existing progress record
        Route::put('/attribute-assignee/{assignee}', [AttributeAssigneeController::class, 'update']);

        // Delete a progress record
        Route::delete('/attribute-assignee/{assignee}', [AttributeAssigneeController::class, 'destroy']);

Route::put('/attribute-assignee', [AttributeAssigneeController::class, 'bulkUpdate']);
Route::delete('/attribute-assignee', [AttributeAssigneeController::class, 'bulkDelete']);


             Route::get('/attribute-accountable', [AttributeAccountableController::class, 'index']);

        // Store a new progress record
        Route::post('/attribute-accountable', [AttributeAccountableController::class, 'store']);

        // Show a single progress record
        Route::get('/attribute-accountable/{accountable}', [AttributeAccountableController::class, 'show']);

        // Update an existing progress record
        Route::put('/attribute-accountable/{accountable}', [AttributeAccountableController::class, 'update']);

        // Delete a progress record
        Route::delete('/attribute-accountable/{accountable}', [AttributeAccountableController::class, 'destroy']);
Route::put('/attribute-accountable', [AttributeAccountableController::class, 'bulkUpdate']);
Route::delete('/attribute-accountable', [AttributeAccountableController::class, 'bulkDelete']);


        // List all Held By records (optional filters: space_id, service_id, attribute_id)
        Route::get('/held-by', [AttributeHeldByController::class, 'index']);

        // Create a new Held By
        Route::post('/held-by', [AttributeHeldByController::class, 'store']);

        // Get a single Held By record with responses (history)
        Route::get('/held-by/{heldBy}', [AttributeHeldByController::class, 'history']);

        // Assignee responds to Held By
        Route::post('/held-by/{heldBy}/respond', [AttributeHeldByController::class, 'respond']);

        // Creator accepts the response
        Route::post('/held-by/{heldBy}/accept', [AttributeHeldByController::class, 'accept']);

        // Creator rejects the response
        Route::post('/held-by/{heldBy}/reject', [AttributeHeldByController::class, 'reject']);
 


    });
});


Route::options('/{any}', function () {
    return response()->json([], 200);
})->where('any', '.*');
