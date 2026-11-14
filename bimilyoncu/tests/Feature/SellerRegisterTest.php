<?php

namespace Tests\Feature;

use App\Models\Seller;
use App\Models\SellerUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerRegisterTest extends TestCase
{
    use RefreshDatabase;

    private function logo(): UploadedFile
    {
        // GD olmadan da çalışsın diye gerçek 1x1 PNG
        return UploadedFile::fake()->createWithContent('logo.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));
    }

    private function register(array $overrides = [])
    {
        return $this->post('/api/seller/auth/register', array_merge([
            'company_name' => 'Çınar Ahşap Atölyesi',
            'logo' => $this->logo(),
            'email' => 'info@cinarahsap.com',
            'password' => 'gizli-sifre-1',
            'password_confirmation' => 'gizli-sifre-1',
        ], $overrides), ['Accept' => 'application/json']);
    }

    public function test_company_and_panel_account_are_created_and_logged_in(): void
    {
        Storage::fake('public');

        $this->register()
            ->assertCreated()
            ->assertJsonPath('user.email', 'info@cinarahsap.com')
            ->assertJsonPath('user.seller.name', 'Çınar Ahşap Atölyesi')
            ->assertJsonPath('user.seller.slug', 'cinar-ahsap-atolyesi')
            // Site yöneticisi onaylayana kadar bekler
            ->assertJsonPath('user.seller.status', 'pending')
            ->assertJsonStructure(['token']);

        $seller = Seller::where('slug', 'cinar-ahsap-atolyesi')->firstOrFail();
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $seller->logo));
        $this->assertTrue(Hash::check('gizli-sifre-1', SellerUser::where('email', 'info@cinarahsap.com')->value('password')));
    }

    public function test_registration_is_validated(): void
    {
        Storage::fake('public');
        $this->register()->assertCreated();

        // Aynı şirket ismi ve e-posta tekrar kullanılamaz; logo zorunlu, şifreler eşleşmeli
        $this->register(['logo' => null, 'password_confirmation' => 'baska'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_name', 'email', 'logo', 'password']);

        $this->assertSame(1, Seller::count());
    }
}
