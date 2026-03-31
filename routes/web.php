<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\dashboard\Analytics;
use App\Http\Controllers\authentications\LoginController;
use App\Http\Controllers\authentications\RegisterBasic;
use App\Http\Controllers\authentications\ResetPasswordBasic;
use App\Http\Controllers\authentications\ForgotPasswordBasic;
use App\Http\Controllers\CommentsController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ReactionsController;
use App\Http\Controllers\ReportsController;
//use App\Http\Controllers\RoleController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\SocietiesController;
use App\Http\Controllers\TagsController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\RulesController;
use App\Http\Controllers\unblockRequestsController;


// Main Page Route
Route::middleware(['guest'])->group(function () {
  // Login Routes
  Route::get('/auth/login', [LoginController::class, 'index'])->name('login');
  // Forgot Password
  Route::get('auth/forgot-password', [ForgotPasswordBasic::class, 'index'])->name('password.request');
  Route::post('auth/forgot-password', [ForgotPasswordBasic::class, 'sendResetLinkEmail'])->name('password.email');
  // Reset Password
  Route::get('auth/reset-password/{token}', [ResetPasswordBasic::class, 'index'])->name('password.reset');
  Route::post('auth/reset-password', [ResetPasswordBasic::class, 'reset'])->name('password.update');
});

Route::post('/auth/login', [LoginController::class, 'login'])->name('user.login');
// Route::post('/auth/register-basic', [RegisterBasic::class, 'register'])->name('auth-register-submit');
Route::post('/auth/logout', [LoginController::class, 'logout'])->name('auth-logout')->middleware('auth');






Route::middleware(['auth'])->group(function () {
  // Main Dashboard Routes
  Route::get('/', [Analytics::class, 'index'])->name('dashboard');
  Route::get('/dashboard', [Analytics::class, 'index'])->name('dashboard.analytics');
  // users Route
  Route::controller(UsersController::class)->group(function () {
    Route::get('users/index/{slug}', 'index')->where('slug', 'society_managers|society_members|society_owners|system_users')->name('users.index')->middleware('permission:listing_system_user|listing_society_owner|listing_society_manager|listing_society_member');
    Route::get('users/create/{slug}', 'create')->name('user.create');
    Route::get('app/user/edit/{uuid}/{slug?}', 'edit')->name('user.edit')->middleware('permission:edit_user');
    Route::post('users/store/{slug?}/{uuid?}', 'storeOrUpdate')->name('user.storeOrUpdate');
    Route::delete('users/delete/{uuid}', 'destroy')->name('users.destroy')->middleware('permission:delete_user');
    Route::post('/users/bulk-delete', 'bulkDelete')->name('users.bulk_delete')->middleware('permission:delete_user');
    Route::get('/users/template-download', 'downloadTemplate')->name('template.download');
    Route::post('users/import', 'importUsers')->name('users.import')->middleware('permission:import_user');
  });

  Route::controller(RolesController::class)->group(function () {
    Route::get('roles/index/{role_id?}', 'index')->name('roles.index')->middleware('permission:listing_role');
    Route::post('role/add',    'store')->name('roles.store');
    Route::put('role/update/{id}', 'update')->name('roles.update')->middleware('permission:edit_role');
    Route::get('role/destroy/{id}', 'destroy')->name('roles.destroy')->middleware('permission:delete_role');
  });

  Route::controller(SocietiesController::class)->group(function () {
    Route::get('society/create/{slug}', 'create')->name('society.create')->middleware('permission:add_society'); // for creation
    Route::get('/societies/{slug}', 'index')->name('societies.index')->middleware('permission:listing_society');
    Route::post('society/store/{slug}/{uuid?}', 'storeOrUpdate')->name('society.store')->middleware('permission:add_society');

    Route::get('/societies/{user_type}/{uuid}', 'show')->name('societies.show');
    Route::get('/societies/{user_type}/{uuid}/{type}/{slug}', 'renderPosts')->name('society.render_posts');
    Route::get('attachment/delete/{id}', 'destroy')->name('attachment.delete');
    Route::post('/societites/bulk_delete', 'bulkDelete')->name('societies.bulk_delete');
    Route::get('society/delete/{slug}/{uuid}', 'deleteSociety')->name('society.delete');
    Route::get('society/block/{slug}/{uuid}', 'blockSociety')->name('society.block');
  });

  Route::post('/society/switch', [\App\Http\Controllers\SocietySwitcherController::class, 'switch'])
    ->name('society.switch');

  Route::controller(PostController::class)->group(function () {
    Route::get('posts/index/{type}', 'index')->where('type', 'discussions|suggestions|issues')->name('posts.index');
    Route::get('posts/create/{type}', 'create')->where('type', 'discussions|suggestions|issues')->name('posts.create');
    Route::get('societies/{user_type}/{uuid}/posts/create/{type}', 'createAdminView')->where('type', 'discussions|suggestions|issues')->name('posts.create_in_admin');
    Route::post('posts/store', 'storeOrUpdate')->name('posts.store');
    Route::get('posts/edit/{type}/{slug}', 'edit')->where('type', 'discussions|suggestions|issues')->name('posts.edit');
    Route::get('societies/{user_type}/{uuid}/posts/edit/{type}/{slug}', 'editInAdmin')->where('type', 'discussions|suggestions|issues')->name('posts.edit_in_admin');

    Route::get('posts/delete/{uuid}', 'destroy')->name('posts.destroy');

    Route::get('posts/view/{type}/{slug}', 'postView')->where('type', 'discussions|suggestions|issues')->name('posts.view');

    Route::get('societies/{user_type}/{uuid}/posts/view/{type}/{slug}/{report?}', 'societyPostView')->where('type', 'discussions|suggestions|issues')
      ->name('society_posts.view');
    Route::get('posts/pin/{uuid}', 'postPin')->where('type', 'discussions|suggestions|issues')->name('posts.pin');

    // handle block request and unblock post in society member case

   // handle block request and unblock post in admins case routes
    Route::get('posts/un-block_request/{identifier}', 'handleUnblockRequest')->where('type', 'discussions|suggestions|issues')->name('posts.unblock_request');
    Route::get('societies/{user_type}/{uuid}/posts/un-block/{identifier}', 'postUnBlock')->where('type', 'discussions|suggestions|issues')->name('posts.unblock');

    // my posts
    Route::get('my_posts/index/{type}/{uuid}', 'index')->where('type', 'discussions|suggestions|issues')->name('my_posts.index');
  });

  Route::controller(CommentsController::class)->group(function () {
    Route::post('comments/store/{slug}', 'store')
      ->where('slug', 'discussions|suggestions|issues')
      ->name('comments.store');

    Route::delete('forum/{slug}/comments/{comment}', 'destroy')
      ->where('slug', 'discussions|suggestions|issues')
      ->name('comments.destroy');
  });


  Route::controller(ReactionsController::class)->group(function () {
    Route::post('/react', 'react')->name('react');
  });


  // admin side
  Route::controller(TagsController::class)->group(function () {
    Route::get('tags/index', 'index')->name('tags.index');
    Route::post('tags/store', 'storeOrUpdate')->name('tags.store');
    Route::delete('tags/delete/{id}', 'destroy')->name('tags.destroy');
    Route::post('/tags/bulk_delete', 'bulkDelete')->name('tags.bulk_delete');
  });

  // Reports routes
  Route::controller(ReportsController::class)->group(function () {
    Route::get('reports/index', 'index')->name('reports.index')->middleware('permission:listing_reports');
    Route::post('report/store', 'store')->name('reports.store');
    // Route::get('/societies/{slug}/{uuid}/{type}/reports/{criteria}', 'show')->name('reports.show');
    Route::get('reports/view/{id}', 'show')->name('reports.show');
    Route::delete('reports/dismiss/{id}', 'dismissReport')->name('reports.dismiss');
    Route::post('/reports/{type}/{id}', 'takeAction')->name('reports.action');
    Route::post('/reports/bulk_delete',  'bulkDelete')->name('reports.bulk_delete');
  });

  // Notification routes
  Route::controller(NotificationsController::class)->group(function () {
    Route::post('notification/read/{id?}', 'markAsRead')->name('notification.read');
    Route::post('notification/delete/{id?}', 'destroy')->name('notification.delete');
  });

  Route::controller(RulesController::class)->group(function () {
    Route::get('rules/index', 'index')->name('rules.index');
    Route::post('rules/store', 'store')->name('rules.store');
    Route::delete('rules/destroy/{id}', 'destroy')->name('rules.destroy');
    Route::post('/rules/bulk_delete',  'bulkDelete')->name('rules.bulk_delete');
  });

  Route::controller(unblockRequestsController::class)->group(function () {
    Route::get('unblock-requests/index/{uuid?}', 'index')->name('requests.index');
    Route::post('unblock-requests/cancel-post/{uuid}', 'requestCancel')->name('requests.cancel');
    Route::post('unblock-requests/accept-post/{uuid}', 'requestApprove')->name('requests.approve');
  });
});
