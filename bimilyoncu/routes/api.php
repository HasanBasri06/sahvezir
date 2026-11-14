<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminSellerController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategorySellerController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductViewController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\Seller\SellerAuthController;
use App\Http\Controllers\Seller\SellerCatalogController;
use App\Http\Controllers\Seller\SellerDashboardController;
use App\Http\Controllers\Seller\SellerOrderController;
use App\Http\Controllers\Seller\SellerProductController;
use App\Http\Controllers\Seller\SellerReportController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => response()->json(['status' => 'ok']));

Route::get('/products', [ProductController::class, 'index']);
// Anasayfa: Web'de Öne Çıkan Ürünler (son 30 günde en çok bakılanlar)
Route::get('/products/trending', [ProductController::class, 'trending']);
Route::get('/products/{slug}', [ProductController::class, 'show']);
// Mağazalar: anasayfa firma şeridi (rastgele 10) ve mağaza sayfası
Route::get('/sellers', [SellerController::class, 'index']);
Route::get('/sellers/{slug}', [SellerController::class, 'show']);
// Kategori ağacı (ana kategori → başlık → alt kategori)
Route::get('/categories', fn () => response()->json(['categories' => \App\Models\Category::tree()]));
// Kategori sayfasındaki satıcı filtresi: bu ana kategoride ürün geliştiren satıcılar
Route::get('/categories/{categorySlug}/sellers', CategorySellerController::class);

// Favorilerim
Route::middleware('auth:sanctum')->prefix('favorites')->group(function () {
    Route::get('/', [FavoriteController::class, 'index']);
    Route::get('/ids', [FavoriteController::class, 'ids']);
    Route::get('/lists', [FavoriteController::class, 'listIndex']);
    Route::post('/{productId}', [FavoriteController::class, 'store']);
    Route::delete('/{productId}', [FavoriteController::class, 'destroy']);
});

// Önceden gezilen ürünler (anasayfa carousel'i)
Route::middleware('auth:sanctum')->prefix('viewed-products')->group(function () {
    Route::get('/', [ProductViewController::class, 'index']);
    Route::post('/{productId}', [ProductViewController::class, 'store'])->middleware('throttle:60,1');
});

// Yorumlar: yalnızca satın alan alıcı yorum yapar; yorum sahibi satıcıyla yazışmaya devam eder
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/products/{slug}/review-status', [ReviewController::class, 'status']);
    Route::post('/products/{slug}/reviews', [ReviewController::class, 'store'])->middleware('throttle:10,1');
    Route::post('/reviews/{review}/replies', [ReviewController::class, 'reply'])->middleware('throttle:20,1');
});

// Adres formu için il ve ilçe listeleri
Route::get('/cities', [LocationController::class, 'cities']);
Route::get('/cities/{city}/districts', [LocationController::class, 'districts']);

Route::prefix('auth')->group(function () {
    Route::middleware('throttle:6,1')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/google', [AuthController::class, 'google']);
    });

    // Şifremi unuttum: kod gönder, kodu doğrula, yeni şifre
    Route::post('/forgot-password', [PasswordResetController::class, 'send'])->middleware('throttle:3,1');
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/forgot-password/verify', [PasswordResetController::class, 'verify']);
        Route::post('/forgot-password/reset', [PasswordResetController::class, 'reset']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/password', [AuthController::class, 'setPassword']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:sanctum')->prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'index']);
    Route::post('/items', [CartController::class, 'store']);
    Route::patch('/items/{key}', [CartController::class, 'update']);
    Route::delete('/items/{key}', [CartController::class, 'destroy']);
    Route::post('/merge', [CartController::class, 'merge']);
});

Route::middleware('auth:sanctum')->prefix('addresses')->group(function () {
    Route::get('/', [AddressController::class, 'index']);
    Route::post('/', [AddressController::class, 'store']);
});

Route::middleware('auth:sanctum')->prefix('orders')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::post('/', [OrderController::class, 'store']);
    Route::get('/{number}', [OrderController::class, 'show']);
    Route::post('/{number}/items/{itemId}/return', [OrderController::class, 'requestReturn'])->whereNumber('itemId');
});

// Satıcı paneli: ayrı "seller" guard'ı; müşteri token'ı burada, satıcı token'ı müşteri uçlarında geçersizdir
Route::prefix('seller')->group(function () {
    Route::post('/auth/login', [SellerAuthController::class, 'login'])->middleware('throttle:6,1');
    // Satıcı Ol: yeni mağaza ve panel hesabı
    Route::post('/auth/register', [SellerAuthController::class, 'register'])->middleware('throttle:3,1');

    Route::middleware('auth:seller')->group(function () {
        Route::get('/auth/me', [SellerAuthController::class, 'me']);
        // Ürün Kataloğum: satıcının alt kategorileri
        Route::get('/catalog', [SellerCatalogController::class, 'show']);
        Route::put('/catalog', [SellerCatalogController::class, 'update']);
        // Katalogdan çıkarılan alt kategorinin ürünlerini başka alt kategoriye aktarır
        Route::post('/catalog/move-products', [SellerCatalogController::class, 'moveProducts']);
        Route::post('/auth/logout', [SellerAuthController::class, 'logout']);
        Route::get('/dashboard', SellerDashboardController::class);
        // Genel Bakış: tarih aralığındaki satışlar, ciro ve kâr (aralık yoksa tüm zamanlar)
        Route::get('/dashboard/summary', [SellerDashboardController::class, 'summary']);

        Route::post('/products', [SellerProductController::class, 'store']);
        Route::post('/order-items/{itemId}/status', [SellerOrderController::class, 'advance'])->whereNumber('itemId');
        // Rapor: tarih aralığı ve ürünlere göre satışların zamanı (timeline)
        Route::get('/reports/sales', [SellerReportController::class, 'sales']);
        // Panel açıkken yeni sipariş bildirimi için düzenli sorgu
        Route::get('/notifications', [SellerOrderController::class, 'notifications']);

        Route::prefix('products/{id}')->controller(SellerProductController::class)->group(function () {
            Route::get('/', 'show');
            Route::patch('/', 'update');
            // Düzenle formu (görsel dosyaları da gelir; PHP dosyaları yalnızca POST'ta okur)
            Route::post('/', 'replace');
            Route::delete('/', 'destroy');
            Route::post('/discount', 'discount');
            Route::delete('/discount', 'removeDiscount');
            Route::post('/images', 'storeImage');
            Route::delete('/images/{imageId}', 'destroyImage')->whereNumber('imageId');
            Route::post('/images/{imageId}/cover', 'coverImage')->whereNumber('imageId');
            Route::post('/reviews/{reviewId}/replies', 'reply')->whereNumber('reviewId');
            Route::delete('/reviews/{reviewId}/replies/{replyId}', 'destroyReply')->whereNumber(['reviewId', 'replyId']);
        });
    });
});

// Site yöneticileri: ayrı "admin" guard'ı
Route::prefix('admin')->group(function () {
    Route::post('/auth/login', [AdminAuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware('auth:admin')->group(function () {
        Route::get('/auth/me', [AdminAuthController::class, 'me']);
        Route::post('/auth/logout', [AdminAuthController::class, 'logout']);

        // Satıcı İşlemleri: onay bekleyenler ve satıcılarımız
        Route::get('/sellers', [AdminSellerController::class, 'index']);
        Route::post('/sellers/{seller}/review', [AdminSellerController::class, 'review']);
        // Katalog İşlemleri → Ürünler: tüm ürünler, 30'ar sayfa, kategoriye göre filtre
        Route::get('/products', [AdminProductController::class, 'index']);
        // Kategoriler: ağaç, ekleme, ad güncelleme
        Route::get('/categories', [AdminCategoryController::class, 'index']);
        Route::post('/categories', [AdminCategoryController::class, 'store']);
        Route::patch('/categories/{category}', [AdminCategoryController::class, 'update']);
        // Satıcılarımız: pasife al / yeniden aktif et
        Route::post('/sellers/{seller}/active', [AdminSellerController::class, 'setActive']);
    });
});
