<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Mail\OtpVerificationMail;
use App\Models\Shipment;
use App\Services\SlackService;
use App\Support\VerificationFileStorage;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ConfirmAccountController extends Controller
{
    use ResponseTrait;

    /**
     * Hesap doğrulama durumunu döndürür (progress bar için).
     * User modelindeki phone_verify, verified, driving_licance_doc, psikoteknik_doc, src_doc, id_image, profile_image alanlarına göre true/false.
     */
    public function home(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return $this->error([], 'Oturum bulunamadı.', [], 401);
        }

        $user->refresh();

        $isCargoOwner = (string) $user->type === 'cargo_owner';
        $listingsRequired = 3;
        $listingsCount = Shipment::where('creater_id', $user->id)->count();
        $listingsDone = $listingsCount >= $listingsRequired;

        $verification = [
            'phone' => (bool) $user->phone_verify,
            // Eski client'lar için alias (ilk adım artık SMS telefon doğrulamasını baz alır).
            'email' => (bool) $user->email_verify,
            'profilePhoto' => ! empty($user->profile_image),
            'identity' => ! empty($user->id_image),
            'src' => ! empty($user->src_doc),
            'psychotechnical' => ! empty($user->psikoteknik_doc),
            'license' => ! empty($user->driving_licance_doc),
            // Yük sahibi için SRC/psikoteknik/ehliyet yerine minimum 3 ilan yayınlama şartı.
            'listings' => $listingsDone,
            'verified' => (bool) $user->verified,
        ];

        // Yük sahiplerinden SRC, psikoteknik ve ehliyet istenmez; yerine 3 ilan şartı gelir.
        $progressKeys = $isCargoOwner
            ? ['email', 'profilePhoto', 'identity', 'listings', 'verified']
            : ['email', 'profilePhoto', 'identity', 'src', 'psychotechnical', 'license', 'verified'];

        $completed = count(array_filter(array_intersect_key($verification, array_flip($progressKeys))));
        $total = count($progressKeys);


        return $this->success([
            'verification' => $verification,
            'completed_count' => $completed,
            'total_count' => $total,
            'progress_percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            'listings_count' => $listingsCount,
            'listings_required' => $listingsRequired,
        ], 'OK');
    }

    /**
     * Hesap e-postasını günceller (benzersizlik kontrolü). Adres değişince doğrulama sıfırlanır.
     */
    public function updateEmail(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return $this->error([], 'Oturum bulunamadı.', [], 401);
        }

        $validator = Validator::make($request->all(), [
            'email' => [
                'required',
                'string',
                'email:rfc',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ], [
            'email.required' => 'E-posta gerekli.',
            'email.email' => 'Geçerli bir e-posta adresi girin.',
            'email.unique' => 'Bu e-posta adresi zaten kullanılıyor.',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Doğrulama hatası', $validator->errors()->toArray(), 422);
        }

        $newEmail = Str::lower(trim((string) $request->email));

        if (Str::lower((string) $user->email) === $newEmail) {
            return $this->success([
                'user' => new UserResource($user->fresh()->loadMissing('pushDevice')),
            ], 'E-posta aynı.');
        }

        $user->update([
            'email' => $newEmail,
            'email_verify' => false,
            'code' => null,
        ]);

        return $this->success([
            'user' => new UserResource($user->fresh()->loadMissing('pushDevice')),
        ], 'E-posta güncellendi. Yeni adresinize gönderilen kod ile doğrulayın.');
    }

    /**
     * E-posta doğrulama kodu gönderir; kodu users.code alanına yazar ve e-posta ile iletir.
     */
    public function sendEmailVerificationCode(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return $this->error([], 'Oturum bulunamadı.', [], 401);
        }

        if ($user->email_verify) {
            return $this->error([], 'Email adresiniz zaten doğrulanmış.', [], 400);
        }

        $email = trim((string) $user->email);
        if ($email === '' || ! str_contains($email, '@')) {
            return $this->error([], 'Hesabınızda kayıtlı geçerli bir e-posta adresi bulunamadı.', [], 422);
        }

        $code = (string) random_int(100000, 999999);
        $user->update(['code' => $code]);

        try {
            Mail::to($email)->send(new OtpVerificationMail($code));
        } catch (\Throwable $e) {
            report($e);

            return $this->error([], 'Doğrulama kodu gönderilemedi. Lütfen daha sonra tekrar deneyin.', [], 503);
        }

        return $this->success([], 'Doğrulama kodu e-posta adresinize gönderildi.');
    }

    /**
     * E-posta doğrulaması: gönderilen kod doğruysa kullanıcının email_verify alanını günceller.
     */
    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|size:6',
        ], [
            'code.required' => 'Doğrulama kodu zorunludur.',
            'code.size' => 'Doğrulama kodu 6 haneli olmalıdır.',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Doğrulama hatası', $validator->errors()->toArray(), 422);
        }

        $user = $request->user();
        if (! $user) {
            return $this->error([], 'Oturum bulunamadı.', [], 401);
        }

        if (Str::trim($user->code) !== Str::trim($request->code)) {
            return $this->error([], 'Doğrulama kodu hatalı.', ['code' => ['Doğrulama kodu hatalı.']], 422);
        }

        $user->update([
            'code' => null,
            'email_verify' => true,
        ]);

        return $this->success([
            'email_verify' => $user->fresh()->email_verify,
        ], 'E-posta adresiniz doğrulandı.');
    }

    /**
     * Tek bir belge yükler; random string ile dosya adı oluşturup ilgili user kolonuna yazar.
     * type: profile_image | id_image | src_doc | psikoteknik_doc | driving_licance_doc
     */
    public function uploadDocument(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:profile_image,id_image,src_doc,psikoteknik_doc,driving_licance_doc',
            'file' => 'required|file|max:5120', // 5MB
        ], [
            'type.required' => 'Belge türü gerekli.',
            'type.in' => 'Geçersiz belge türü.',
            'file.required' => 'Dosya seçin.',
            'file.max' => 'Dosya en fazla 5MB olabilir.',
        ]);

        if ($validator->fails()) {
            return $this->error([], 'Doğrulama hatası', $validator->errors()->toArray(), 422);
        }

        $user = $request->user();
        if (! $user) {
            return $this->error([], 'Oturum bulunamadı.', [], 401);
        }

        $type = $request->type;
        $file = $request->file('file');

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        if (! in_array($file->getMimeType(), $allowedMimes, true)) {
            return $this->error([], 'Geçersiz dosya türü. Sadece JPG, PNG, WEBP veya PDF yükleyebilirsiniz.', [], 422);
        }

        if ($type === 'profile_image') {
            $allowedImageMimes = ['image/jpeg', 'image/png', 'image/webp'];
            if (! in_array($file->getMimeType(), $allowedImageMimes, true)) {
                return $this->error([], 'Profil fotoğrafı için sadece JPG, PNG veya WEBP yükleyebilirsiniz.', [], 422);
            }
            if ($file->getSize() > 5 * 1024 * 1024) {
                return $this->error([], 'Profil fotoğrafı en fazla 5MB olabilir.', [], 422);
            }
        }

        $ext = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin';
        $filename = Str::random(40).'.'.strtolower($ext);

        $column = match ($type) {
            'profile_image' => 'profile_image',
            'id_image' => 'id_image',
            'src_doc' => 'src_doc',
            'psikoteknik_doc' => 'psikoteknik_doc',
            'driving_licance_doc' => 'driving_licance_doc',
            default => null,
        };

        $oldRelativePath = ($column && ! empty($user->{$column})) ? (string) $user->{$column} : null;
        $oldRelativePath = $oldRelativePath !== '' ? str_replace('\\', '/', $oldRelativePath) : null;

        try {
            $bytes = $file->getContent();
        } catch (\Throwable) {
            return $this->error([], 'Dosya okunamadı.', [], 422);
        }
        if ($bytes === '') {
            return $this->error([], 'Dosya boş.', [], 422);
        }

        try {
            $path = VerificationFileStorage::put($type, $filename, $bytes);
        } catch (\Throwable $e) {
            report($e);

            return $this->error([], 'Dosya public dizinine yazılamadı.', [], 500);
        }

        if ($column) {
            $user->update([$column => $path]);
        }

        if ($oldRelativePath && $oldRelativePath !== $path) {
            VerificationFileStorage::delete($oldRelativePath);
        }

        return $this->success([
            'path' => $path,
            'verification' => [
                'phone' => (bool) $user->phone_verify,
                'email' => (bool) $user->fresh()->email_verify,
                'profilePhoto' => ! empty($user->fresh()->profile_image),
                'identity' => ! empty($user->fresh()->id_image),
                'src' => ! empty($user->fresh()->src_doc),
                'psychotechnical' => ! empty($user->fresh()->psikoteknik_doc),
                'license' => ! empty($user->fresh()->driving_licance_doc),
            ],
        ], 'Belge yüklendi.');
    }

    /**
     * Tüm doğrulama adımları tamamlandığında hesap etkinleştirme talebini Slack'e iletir.
     */
    public function requestActivation(Request $request, SlackService $slackService)
    {
        $user = $request->user();
        if (! $user) {
            return $this->error([], 'Oturum bulunamadı.', [], 401);
        }

        $user->refresh();

        if ((string) $user->type === 'cargo_owner') {
            $listingsCount = Shipment::where('creater_id', $user->id)->count();
            $stepsComplete = $user->email_verify
                && ! empty($user->profile_image)
                && ! empty($user->id_image)
                && $listingsCount >= 3;
        } else {
            $stepsComplete = $user->email_verify
                && ! empty($user->profile_image)
                && ! empty($user->id_image)
                && ! empty($user->src_doc)
                && ! empty($user->psikoteknik_doc)
                && ! empty($user->driving_licance_doc);
        }

        if (! $stepsComplete) {
            return $this->error([], 'Hesap etkinleştirmek için tüm doğrulama adımlarını tamamlayın.', [], 422);
        }

        $channel = (string) config('slack.notifications.account_activation');
        if ($channel !== '') {
            $message = __('slack.account_activation_request', [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'phone' => $user->phone,
            ], 'tr');

            try {
                $slackService->sendMessage($message, $channel, SlackService::COLOR_SUCCESS);
            } catch (\Throwable $e) {
                report($e);

                return $this->error([], 'Bildirim gönderilemedi. Lütfen daha sonra tekrar deneyin.', [], 503);
            }
        }

        return $this->success([], 'Hesap etkinleştirme talebiniz alındı.');
    }
}
