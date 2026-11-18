<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConfirmAccountController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\LoadAlarmController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ShipmentAttachmentController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\CarController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\OAuthController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PushNotificationController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SMSSenderController;
use App\Http\Controllers\VehicleOwnerController;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;

Route::controller(LogController::class)
    ->prefix('/log')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::post('/call-request', 'callRequest');
    });

Route::controller(AdminController::class)
    ->prefix('admin')
    ->group(function () {
        Route::post('/login', 'login');
        Route::middleware(['auth:sanctum'])->get('/check', 'check');
        Route::middleware(['auth:sanctum'])->get('/dashboard', 'dashboard');
        Route::middleware(['auth:sanctum'])->get('/users', 'searchUsers');
        Route::middleware(['auth:sanctum'])->post('/sms', 'sendSms');
        Route::middleware(['auth:sanctum'])->get('/users/all', 'listUsers');
        Route::middleware(['auth:sanctum'])->get('/users/documents', 'listUserDocuments');
        Route::middleware(['auth:sanctum'])->post('/users/{user}/approve', 'approveUser');
        Route::middleware(['auth:sanctum'])->post('/users/{user}/revoke-approval', 'revokeUserApproval');
        Route::middleware(['auth:sanctum'])->post('/users/{user}/reject', 'rejectUser');
        Route::middleware(['auth:sanctum'])->post('/users/{user}/extend-payment', 'extendUserPayment');
        Route::middleware(['auth:sanctum'])->delete('/users/{user}', 'deleteUser');
        Route::middleware(['auth:sanctum'])->post('/users', 'storeUser');
        Route::middleware(['auth:sanctum'])->get('/search-analysis', 'listSearchAnalysis');
        Route::middleware(['auth:sanctum'])->get('/shipments', 'listShipments');
        Route::middleware(['auth:sanctum'])->get('/shipments/analytics', 'shipmentAnalytics');
        Route::middleware(['auth:sanctum'])->delete('/shipments/{shipment}', 'deleteShipment');
        Route::middleware(['auth:sanctum'])->post('/shipments', 'storeShipment');
        Route::middleware(['auth:sanctum'])->get('/cars', 'listCars');
        Route::middleware(['auth:sanctum'])->post('/cars', 'storeCar');
        Route::middleware(['auth:sanctum'])->match(['put', 'post'], '/cars/{car}', 'updateCar');
        Route::middleware(['auth:sanctum'])->get('/cities', 'listCities');
        Route::middleware(['auth:sanctum'])->put('/cities/{city}', 'updateCity');
        Route::middleware(['auth:sanctum'])->get('/cities/{city}/districts', 'listCityDistricts');
        Route::middleware(['auth:sanctum'])->post('/cities/{city}/districts', 'storeDistrict');
        Route::middleware(['auth:sanctum'])->put('/districts/{district}', 'updateDistrict');
        Route::middleware(['auth:sanctum'])->delete('/districts/{district}', 'deleteDistrict');
        Route::middleware(['auth:sanctum'])->get('/post-types', 'listPostTypes');
        Route::middleware(['auth:sanctum'])->post('/post-types', 'storePostType');
        Route::middleware(['auth:sanctum'])->match(['put', 'post'], '/post-types/{postType}', 'updatePostType');
    });

Route::controller(SettingController::class)
    ->prefix('settings')
    ->group(function () {
        Route::get('/app-version', 'appVersion');
    });

Route::controller(AuthController::class)
    ->prefix('auth')
    ->group(function () {
        Route::post('/login', 'login');
        Route::post('/google', 'googleLogin')->middleware('throttle:20,1');
        Route::post('/google/register', 'googleRegister')->middleware('throttle:10,1');
        Route::post('/register', 'register');
        Route::post('/verify-otp', 'verifyOtp');
        Route::post('/resend-otp', 'resendOtp');
        Route::post('/change-phone-pending', 'changePhonePending');
        Route::post('/forgot-password/send-code', 'sendForgotPasswordCode');
        Route::post('/forgot-password/verify-code', 'verifyForgotPasswordCode');
        Route::post('/forgot-password/reset', 'resetForgotPassword');
        Route::middleware(['auth:sanctum', 'user.active'])->get('/check', 'check');
        Route::middleware(['auth:sanctum', 'user.active'])->post('/panel-tour-complete', 'markPanelTourComplete');
        Route::middleware(['auth:sanctum', 'user.active'])->put('/user-location', 'updateUserLocation');
        Route::middleware(['auth:sanctum', 'user.active'])->delete('/user-location', 'clearUserLocation');
        Route::middleware(['auth:sanctum', 'user.active'])->put('/suitable-postings', 'updateSuitablePostings');
        Route::middleware(['auth:sanctum', 'user.active'])->get('/close-users', 'getCloseUsers');
        Route::middleware(['auth:sanctum', 'user.active'])->post('/create-car-by-user', 'createCarByUser');
        Route::middleware(['auth:sanctum', 'user.active'])->post('/multi-vehicle-mode', 'enableMultiVehicleMode');
        Route::middleware(['auth:sanctum', 'user.active'])->post('/send-password-change-code', 'sendPasswordChangeCode');
        Route::middleware(['auth:sanctum', 'user.active'])->put('/change-password', 'changePassword');
        Route::middleware(['auth:sanctum', 'user.active'])->post('/delete', 'delete');
    });


Route::controller(ShipmentController::class)
    ->group(function () {
        Route::get('/post-types', 'getPostTypes');
    });

Route::controller(ShipmentAttachmentController::class)
    ->middleware(['auth:sanctum', 'user.active'])
    ->group(function () {
        Route::get('/shipments/{shipmentId}/attachments', 'index');
        Route::post('/shipments/{shipmentId}/attachments', 'store');
    });

Route::controller(ShipmentController::class)
    ->middleware(['auth:sanctum', 'user.active'])
    ->group(function () {
        Route::get('/shipments', 'getAll')->where('filters', 'nullable|array');
        Route::get('/shipments/creator/{creatorId}', 'getByCreatorId');
        Route::get('/shipments/active-routes', 'activeRoutes');
        Route::get('/shipments/{slug}/requests', 'getBySlugWithRequests');
        Route::post('/shipments/{slug}/requests', 'storeRequest');
        Route::post('/shipments/{slug}/requests/{requestId}/accept', 'acceptRequest');
        Route::post('/shipments/{slug}/call-outcome', 'reportCallOutcome');
        Route::get('/shipments/{slug}/call-requests', 'listCallRequests');
        Route::post('/shipments/{slug}/call-requests/{callRequestId}/accept', 'acceptCallRequest');
        Route::get('/shipments/{slug}', 'getBySlug');
        Route::post('/shipments/create', 'create');
        Route::post('/shipments/intercity', 'intercityShipments');
        Route::post('/shipments/{id}/cancel', 'cancel');
    });

Route::controller(ConfirmAccountController::class)
    ->prefix('confirm-account')
    ->middleware(['auth:sanctum', 'user.active'])
    ->group(function () {
        Route::get('/home', 'home');
        Route::put('/email', 'updateEmail');
        Route::post('/send-email-code', 'sendEmailVerificationCode');
        Route::post('/verify-email', 'verifyEmail');
        Route::post('/send-phone-code', 'sendEmailVerificationCode');
        Route::post('/verify-phone', 'verifyEmail');
        Route::post('/upload-document', 'uploadDocument');
        Route::post('/request-activation', 'requestActivation');
    });

Route::controller(LocationController::class)
    ->prefix('locations')
    ->group(function () {
        Route::get('/cities', 'cities');
        Route::get('/cities/{cityId}/districts', 'districts');
    });

Route::controller(CarController::class)
    ->group(function () {
        Route::get('/cars', 'getAll');
        Route::middleware(['auth:sanctum', 'user.active'])->group(function () {
            Route::get('/cars/my', 'getCarsByUser');
            Route::put('/cars/my/{id}', 'updateUserCar');
            Route::delete('/cars/my/{id}', 'deleteUserCar');
        });
    });

Route::post('/broadcasting/auth', [BroadcastController::class, 'authenticate'])
    ->middleware(['auth:sanctum', 'user.active']);

Route::controller(MessageController::class)
    ->prefix('messages')
    ->group(function () {
        Route::post('/create', 'create');
        Route::get('/by-sender-receiver', 'getAllMessageBySenderAndReceiverId');
        Route::get('/by-user', 'getAllMessageByUserId');
        Route::get('/conversation', 'getConversationDetail');
        Route::put('/mark-read', 'markAsRead')->middleware(['auth:sanctum', 'user.active']);
        Route::get('/has-unread', 'hasUnread')->middleware(['auth:sanctum', 'user.active']);
        Route::get('/unread-count', 'unreadCount')->middleware(['auth:sanctum', 'user.active']);
    });

Route::controller(OAuthController::class)
    ->prefix('auth')
    ->middleware('web')
    ->group(function () {
        Route::prefix('google')->group(function () {
            Route::get('/redirect', 'redirect');
            Route::get('/callback', 'callback');
        });
    });

Route::controller(VehicleOwnerController::class)
    ->prefix('vehicle')
    ->middleware(['auth:sanctum', 'user.active']) 
    ->group(function () {
        Route::get('/', 'index');
        Route::get('/orders', 'getOrders');
        Route::get('/active-jobs', 'activeJobs');
        Route::post('/orders/{shipmentId}/finish-drive', 'finishDrive');
        Route::post('/orders/{shipmentId}/cancel-drive', 'cancelDrive');
        Route::post('/orders/{shipmentId}/vehicle', 'selectVehicle');
        Route::post('/orders/{shipmentId}/start', 'startJob');
        Route::post('/orders/{shipmentId}/delegate', 'delegateJob');
        Route::get('/fleet-jobs', 'fleetJobs');
        // Genel Bakış: araçlar + araç başına tamamlanan iş, toplam km
        Route::get('/stats', 'vehicleStats');
        Route::post('/location', 'updateLocation');
    });

Route::controller(LoadAlarmController::class)
    ->prefix('load-alarms')
    ->middleware(['auth:sanctum', 'user.active'])
    ->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::put('/{id}', 'update')->whereNumber('id');
        Route::delete('/{id}', 'destroy')->whereNumber('id');
    });

Route::controller(CardController::class)
    ->prefix('cards')
    ->middleware(['auth:sanctum', 'user.active'])
    ->group(function () {
        Route::get('/', 'getAllByUser');
        Route::post('/', 'store');
        Route::delete('/{card}', 'destroy');
    });

Route::controller(CommentController::class)
    ->prefix('comments')
    ->middleware(['auth:sanctum', 'user.active'])
    ->group(function () {
        Route::get('/count', 'countByUser');
        Route::get('/', 'getAllByUser');
    });

Route::controller(AddressController::class)
    ->prefix('addresses')
    ->middleware(['auth:sanctum', 'user.active'])
    ->group(function () {
        Route::get('/', 'getAllByUser');
        Route::post('/', 'store');
        Route::put('/{address}', 'update');
    });

Route::controller(ContactController::class)
    ->prefix('contact')
    ->group(function () {
        Route::post('/', 'store')->middleware(['auth:sanctum', 'user.active']);
    });


Route::controller(PaymentController::class)
    ->prefix('payment')
    ->group(function () {
        Route::post('/callback', 'callback');
        Route::match(['get', 'post'], '/return/ok', 'merchantReturnOk');
        Route::match(['get', 'post'], '/return/fail', 'merchantReturnFail');
        Route::get('/3ds/{token}', 'showThreeDsPage')->where('token', '[A-Za-z0-9]+');
        Route::post('/create', 'createPayment')->middleware(['auth:sanctum', 'user.active']);
        Route::post('/sync', 'syncPaymentStatus')->middleware(['auth:sanctum', 'user.active']);
        Route::get('/subscription', 'subscription')->middleware(['auth:sanctum', 'user.active']);
        Route::get('/packages', 'packages');
        Route::patch('/subscription/auto-renew', 'setAutoRenew')->middleware(['auth:sanctum', 'user.active']);
        Route::post('/vehicle/{carId}/charge', 'chargeVehicle')->whereNumber('carId')->middleware(['auth:sanctum', 'user.active']);
    });

Route::controller(PushNotificationController::class)
    ->prefix('notifications')
    ->middleware(['auth:sanctum', 'user.active'])
    ->group(function () {
        Route::post('/register-token', 'registerToken');
        Route::delete('/device', 'clearDevice');
        Route::post('/device/clear', 'clearDevice');
        Route::post('/send-notification', 'sendNotification');
    });