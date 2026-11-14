<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    use ResponseTrait;

    /**
     * Zorunlu minimum uygulama sürümü.
     * `?platform=ios` veya `?platform=android` — mobil her platform için ayrı kontrol eder.
     */
    public function appVersion(Request $request)
    {
        $setting = Setting::current();

        $platform = strtolower(trim((string) $request->query('platform', '')));
        if (! in_array($platform, ['ios', 'android'], true)) {
            $platform = 'android';
        }

        return $this->success([
            'platform' => $platform,
            'version' => $setting->versionForPlatform($platform),
            'ios_version' => $setting->ios_version,
            'android_version' => $setting->android_version,
        ]);
    }
}
