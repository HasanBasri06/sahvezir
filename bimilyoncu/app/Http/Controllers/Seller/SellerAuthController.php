<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Seller;
use App\Models\SellerUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Satıcı paneli girişi; müşteri hesaplarından (users) tamamen ayrıdır.
 */
class SellerAuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $sellerUser = SellerUser::with('seller')->where('email', $request->validated('email'))->first();

        if (! $sellerUser || ! Hash::check($request->validated('password'), $sellerUser->password)) {
            throw ValidationException::withMessages([
                'email' => 'E-posta veya şifre hatalı.',
            ]);
        }

        return response()->json([
            'user' => $this->userPayload($sellerUser),
            'token' => $sellerUser->createToken('seller-panel')->plainTextToken,
        ]);
    }

    /**
     * Satıcı Ol: şirket (mağaza) ve ona bağlı panel hesabı birlikte açılır, hesap hemen giriş yapar.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'min:2', 'max:100', 'unique:sellers,name'],
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'email' => ['required', 'email', 'max:255', 'unique:seller_users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'company_name.required' => 'Şirket ismini yazın.',
            'company_name.min' => 'Şirket ismi en az 2 karakter olmalıdır.',
            'company_name.unique' => 'Bu isimde bir mağaza zaten var.',
            'logo.required' => 'Şirket logosunu yükleyin.',
            'logo.image' => 'Logo bir görsel olmalıdır.',
            'logo.mimes' => 'Logo PNG, JPG ya da WEBP olmalıdır.',
            'logo.max' => 'Logo en fazla 2 MB olabilir.',
            'email.required' => 'E-posta zorunludur.',
            'email.email' => 'Geçerli bir e-posta girin.',
            'email.unique' => 'Bu e-posta ile bir satıcı hesabı zaten var.',
            'password.required' => 'Şifre zorunludur.',
            'password.confirmed' => 'Şifreler eşleşmiyor.',
            'password.min' => 'Şifre en az 8 karakter olmalıdır.',
        ]);

        $path = $data['logo']->store('saticilar', 'public');
        try {
            $sellerUser = DB::transaction(function () use ($data, $path) {
                $seller = Seller::create([
                    'slug' => $this->uniqueSlug($data['company_name']),
                    'name' => $data['company_name'],
                    'logo' => '/storage/'.$path,
                    // Site yöneticisi onaylayana kadar ürün ekleyemez
                    'status' => Seller::STATUS_PENDING,
                ]);

                return $seller->users()->create([
                    'name' => $data['company_name'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                ]);
            });
        } catch (\Throwable $e) {
            // Kayıt oluşmadıysa yüklenen logo da kalmasın
            Storage::disk('public')->delete($path);
            throw $e;
        }

        return response()->json([
            'user' => $this->userPayload($sellerUser->load('seller')),
            'token' => $sellerUser->createToken('seller-panel')->plainTextToken,
        ], 201);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'magaza';
        $slug = $base;
        for ($i = 2; Seller::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user()->load('seller'))]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Çıkış yapıldı.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(SellerUser $sellerUser): array
    {
        return [
            'id' => $sellerUser->id,
            'name' => $sellerUser->name,
            'email' => $sellerUser->email,
            'seller' => [
                'slug' => $sellerUser->seller->slug,
                'name' => $sellerUser->seller->name,
                'logo' => $sellerUser->seller->logo,
                'brand_color' => $sellerUser->seller->brand_color,
                'status' => $sellerUser->seller->status,
                // Ürün Kataloğum'da seçilen alt kategoriler; onaylı mağaza boşsa önce katalog seçmeli
                'category_ids' => $sellerUser->seller->categories()->pluck('category_id'),
            ],
        ];
    }
}
