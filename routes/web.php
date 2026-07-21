<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\authentications\ForgotPasswordBasic;
use App\Http\Controllers\authentications\LoginController;
use App\Http\Controllers\authentications\ResetPasswordBasic;
use App\Http\Controllers\BlocksController;
use App\Http\Controllers\CommentsController;
use App\Http\Controllers\dashboard\Analytics;
use App\Http\Controllers\frontend\landingPageController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PropertiesController;
use App\Http\Controllers\ReactionsController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\RulesController;
use App\Http\Controllers\SocietiesController;
use App\Http\Controllers\TagsController;
use App\Http\Controllers\UnblockRequestsController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

// Route::middleware(['guest'])->group(function () {
Route::controller(LandingPageController::class)->group(function () {
    Route::get('/', 'index')->name('landing-page');
    Route::match(['get', 'post'], 'property/search', 'findProperty')->name('property.search');
    Route::get('property/{uuid}/details', 'show')->name('property.show');
    Route::get('property-unit/{uuid}/details', 'showUnit')->name('property.unit.show');
    Route::post('/property/favourite', 'toggleFavourite')->name('property.favourite');
    Route::get('front-page/favourites', 'favourites')->name('favourites.show');
    Route::get('front-page/contact-us', 'contactUs')->name('contact-us.show');
    Route::post('/contact/send', 'sendContactMessage')->name('contact.send');
    Route::get('/geocode', 'geocode')->name('property.geocode');
});
Route::get('/auth/login', [LoginController::class, 'index'])->name('login');
// Forgot Password
Route::get('auth/forgot-password', [ForgotPasswordBasic::class, 'index'])->name('password.request');
Route::post('auth/forgot-password', [ForgotPasswordBasic::class, 'sendResetLinkEmail'])->name('password.email');
// Reset Password
Route::get('auth/reset-password/{token}', [ResetPasswordBasic::class, 'index'])->name('password.reset');
Route::post('auth/reset-password', [ResetPasswordBasic::class, 'reset'])->name('password.update');
// });

Route::post('/auth/login', [LoginController::class, 'login'])->name('user.login');
Route::post('/auth/logout', [LoginController::class, 'logout'])->name('auth-logout')->middleware('auth');

Route::controller(\App\Http\Controllers\PropertyInquiryController::class)->group(function () {
    Route::get('inquiries/index', 'index')->name('inquiry.index');
    Route::post('property-inquiry', 'storeOrUpdate')->name('inquiry.store');
    Route::get('/inquiries/{listing:uuid}', 'inquiriesWithListing')->name('inquiries.show');
    Route::get('societies/{user_type}/society/{society_uuid}/inquiries/{listing:uuid}', 'propertyInquiriesFromSociety')->name('society.property.inquiries.show');
    Route::get('/{type}/details/{property_uuid}/{uuid}', 'propertyInquiries')
        ->name('property.inquiries.show')
        ->where([
            'property_uuid' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}',
            'uuid' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}',
        ]);
    Route::delete('/inquiries/dismiss/{inquiry}', 'dismiss')->name('inquiries.dismiss');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [Analytics::class, 'index'])->name('dashboard.analytics');
    Route::controller(UsersController::class)->group(function () {
        Route::get('users/index/{slug}', 'index')->where('slug', 'society_managers|society_members|society_owners|system_users')->name('users.index')->middleware('permission:listing_system_user|listing_society_owner|listing_society_manager|listing_society_member');
        Route::get('users/create/{slug}', 'create')->name('user.create');
        Route::get('app/user/edit/{uuid}/{slug?}', 'edit')->name('user.edit')->middleware('permission:edit_user');
        Route::post('users/store/{slug?}/{uuid?}', 'storeOrUpdate')->name('user.storeOrUpdate');
        Route::delete('users/delete/{uuid}', 'destroy')->name('users.destroy')->middleware('permission:delete_user');
        Route::post('/users/bulk-delete', 'bulkDelete')->name('users.bulk_delete')->middleware('permission:delete_user');
        Route::get('/users/template-download', 'downloadTemplate')->name('users_import_template.download');
        Route::post('users/import', 'importUsers')->name('users.import')->middleware('permission:import_user');
    });

    Route::controller(RolesController::class)->group(function () {
        Route::get('roles/index/{role_id?}', 'index')->name('roles.index')->middleware('permission:listing_role');
        Route::post('role/add', 'store')->name('roles.store');
        Route::put('role/update/{id}', 'update')->name('roles.update')->middleware('permission:edit_role');
        Route::get('role/destroy/{id}', 'destroy')->name('roles.destroy')->middleware('permission:delete_role');
    });

    Route::controller(SocietiesController::class)->group(function () {
        Route::get('society/create/{slug}/{uuid?}', 'create')->name('society.create')->middleware('permission:add_society'); // for creation
        Route::get('/societies/{slug}', 'index')->name('societies.index')->middleware('permission:listing_society');
        Route::post('society/store/{slug}/{uuid?}', 'storeOrUpdate')->name('society.store')->middleware('permission:add_society');

        Route::get('/societies/{user_type}/{uuid}', 'show')->name('societies.show');
        Route::get('/societies/{user_type}/{uuid}/{type}/{slug}', 'renderPosts')->name('society.render_posts');
        Route::get('attachment/delete/{id}', 'destroy')->name('attachment.delete');
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
        Route::delete('tags/delete/{uuid}', 'destroy')->name('tags.destroy');
        Route::post('/tags/bulk_delete', 'bulkDelete')->name('tags.bulk_delete');
    });

    // Reports routes
    Route::controller(ReportsController::class)->group(function () {
        Route::get('reports/index', 'index')->name('reports.index')->middleware('permission:listing_reports');
        Route::post('report/store', 'store')->name('reports.store');
        // Route::get('/societies/{slug}/{uuid}/{type}/reports/{criteria}', 'show')->name('reports.show');
        Route::get('reports/view/{slug}', 'show')->name('reports.show');
        Route::delete('reports/dismiss/{id}', 'dismissReport')->name('reports.dismiss');
        Route::post('/reports/{type}/{id}', 'takeAction')->name('reports.action');
        Route::post('/reports/bulk_delete', 'bulkDelete')->name('reports.bulk_delete');
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
        Route::post('/rules/bulk_delete', 'bulkDelete')->name('rules.bulk_delete');
    });

    Route::controller(UnblockRequestsController::class)->group(function () {
        Route::get('unblock-requests/index/{user_type?}/{uuid?}/{type?}', 'index')->name('requests.index');
        Route::post('unblock-requests/cancel-post/{uuid}', 'requestCancel')->name('requests.cancel');
        Route::post('unblock-requests/accept-post/{uuid}', 'requestApprove')->name('requests.approve');
        Route::post('/requested_posts/bulk_cancel', 'bulkCancel')->name('requested_posts.cancel');
        Route::post('/requested_posts/bulk_approve', 'bulkApprove')->name('requested_posts.approve');

    });

    // Properties Management
    Route::controller(BlocksController::class)->group(function () {
        Route::get('blocks/index', 'index')->name('blocks.index')->middleware('permission:listing_block');
        Route::post('blocks/store', 'store')->name('blocks.store')->middleware('permission:add_block');
        Route::delete('blocks/destroy/{uuid}', 'destroy')->name('blocks.destroy')->middleware('permission:delete_block');
        Route::post('/blocks/bulk_delete', 'bulkDelete')->name('blocks.bulk_delete')->middleware('permission:delete_block');
    });

    Route::controller(PropertiesController::class)->group(function () {
        Route::get('blocks/view/{uuid}/{isPending?}', 'propertiesFromBlocksShow')->name('block.view')->middleware('permission:view_block');

        Route::get('societies/{user_type}/{society_uuid}/properties/block/{uuid}/view', 'propertiesFromSocietyShow')->name('society.block.view')->middleware('permission:view_block');

        //        to show properties to properties owner
        Route::get('owner_properties/view', 'ownerProperties')->name('owner.properties.show')->middleware('permission:owner_properties');

        Route::get('properties_import_template/{type}/download', 'download')->name('properties_import_template.download')->middleware('permission:create_property');
        Route::post('properties/import', 'import')->name('properties.import')->middleware('permission:create_property');
        Route::get('/blocks/{block_uuid}/import-progress', 'importProgress')
            ->name('properties.import_properties');

        Route::get('/blocks/{block_uuid}/import-errors/download', 'downloadImportErrors')
            ->name('properties.import_errors_download');

        Route::get('societies/{user_type}/society/property/create/{block_uuid}/{uuid?}', 'createFromSociety')->name('society.property.create')->middleware('permission:create_property');

        Route::get('property/create/{block_uuid}/{uuid?}', 'createProperty')->name('property.create')->middleware('permission:create_property');
        Route::post('property/store', 'storeOrUpdate')->name('property.store')->middleware('permission:create_property');
        Route::get('property/details/{uuid}', 'propertyDetails')->name('property.details');

        Route::get('owner-property/details/{uuid}', 'ownerPropertyDetails')->name('owner.property.details');

        Route::get('societies/{user_type}/society/{society_uuid}/properties/{uuid}', 'propertyDetailsFromSociety')->name('society.property.details');

        Route::get('{section}/destroy/{uuid}', 'destroy')
            ->name('property.destroy')
            ->where('section', 'property|floor|unit|room')
            ->middleware('permission:delete_property');

        Route::delete('blocks/{block_uuid}/import-errors', 'deleteImportErrors')
            ->name('properties.import_errors_delete');

        Route::get('blocks/{block_uuid}/import-errors-files', 'findImportErrorsFiles')->name('properties.import_errors_files');

        Route::post('/construction/bulk-delete', 'bulkDelete')->name('construction.bulk_delete');

        Route::get('/floor/{floor_id}/units', 'unitsByFloor')->name('floor.units');
        Route::get('/floor/{floor_id}/rooms', 'roomsByFloor');

        Route::get('property/{uuid}/{user_type?}', 'destroyProperty')
            ->name('property.delete')
            ->middleware('permission:delete_property');

    });

    Route::controller(\App\Http\Controllers\PropertyUnitController::class)->group(function () {

        Route::get('unit/details/{uuid}/{isOwner?}', 'show')->name('unit.details');
        Route::get('societies/{user_type}/society/{society_uuid}/unit/{uuid}', 'unitFromSociety')
            ->name('society.unit.details');

        //        Route::get('unit/details/{uuid}/', 'ownerUnitDetails')->name('owner.unit.details');

        Route::post('unit/store', 'update')->name('unit.store')->middleware('permission:edit_property');
        Route::get('unit/{uuid}/{user_type?}', 'destroy')
            ->name('unit.destroy')
            ->middleware('permission:delete_property');

        Route::post('/unit/add', 'add')->name('unit.add');
        Route::post('/unit/update', 'update')->name('unit.update');
        Route::post('/unit/clone', 'clone')->name('unit.clone');
    });

    Route::controller(\App\Http\Controllers\RoomController::class)->group(function () {
        Route::post('/room/store', 'storeOrUpdate')->name('floor.room.store');
        Route::get('/room/{room}', 'getDimensions')->name('floor.room.show');
    });

    Route::controller(\App\Http\Controllers\PropertyListingController::class)->group(function () {
        Route::post('property-listing', 'store')->name('listing.store');
        // web.php
        Route::get('property_listing/destroy/{uuid}', 'destroy')->name('listing.destroy');

    });

    Route::controller(\App\Http\Controllers\PropertyAttributeController::class)->group(function () {
        Route::get('property_attributes/index', 'index')->name('attributes.index')->middleware('permission:listing_attribute');
        Route::post('property_attributes/store', 'store')->name('attributes.store')->middleware('permission:create_attribute');
        Route::delete('property_attributes/delete', 'destroy')->name('attributes.destroy')->middleware('permission:delete_attribute');
        Route::post('property_attributes/bulk_delete', 'bulkDelete')->name('attributes.bulk_delete')->middleware('permission:delete_attribute');

    });

    Route::controller(\App\Http\Controllers\PaymentPlanningTemplateController::class)->group(function () {
        Route::get('payment-planning-templates/index', 'index')->name('payment-planning-templates.index')->middleware('permission:payment_planning_template_listing');
        Route::post('/payment-planning-templates', 'store')
            ->name('payment-planning-templates.store');

        Route::delete('/payment-planning-templates/{paymentPlanningTemplate}', 'destroy')
            ->name('payment-planning-templates.destroy')->middleware('permission:payment_planning_template_delete');

        Route::post('/payment-planning-templates/bulkdetele', 'bulkDelete')->name('payment-planning-templates.bulk_delete')->middleware('permission:payment_planning_template_delete');
    });

    // Attachment controller
    Route::controller(AttachmentController::class)->group(function () {
        Route::post('/attachments/bulk_delete', 'bulkDelete')->name('attachments.bulk_delete');
        Route::get('/attachments/progress', 'fileUploadProgress');

    });

    Route::controller(\App\Http\Controllers\MessageController::class)->group(function () {
        Route::get('/messages/index', 'index')->middleware('permission:contact_messages');
        Route::delete('/messages/delete/{uuid}', 'destroy')->name('message.destroy')->middleware('permission:contact_messages');
        Route::post('/messages/bulk_delete', 'bulkDelete')->name('messages.bulk_delete')->middleware('permission:contact_messages');
        Route::post('/messages/reply', 'reply')->name('messages.reply');
    });

});
