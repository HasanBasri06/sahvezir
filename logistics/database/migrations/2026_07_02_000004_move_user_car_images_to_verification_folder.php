<?php

use App\Support\UserCarImageUploader;
use App\Support\VerificationFileStorage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

return new class extends Migration
{
    public function up(): void
    {
        VerificationFileStorage::ensureDirectory(UserCarImageUploader::STORAGE_TYPE);

        $rows = DB::table('user_cars')
            ->select(['id', 'image_one', 'image_two'])
            ->get();

        foreach ($rows as $row) {
            $updates = [];
            foreach (['image_one', 'image_two'] as $column) {
                $path = $row->{$column};
                if (! is_string($path) || $path === '') {
                    continue;
                }
                $normalized = str_replace('\\', '/', ltrim($path, '/'));
                if (str_starts_with($normalized, VerificationFileStorage::PATH_PREFIX)) {
                    continue;
                }
                if (! str_starts_with($normalized, 'user_cars/')) {
                    continue;
                }

                $filename = basename($normalized);
                $oldAbsolute = public_path($normalized);
                $newRelative = VerificationFileStorage::relativePath(UserCarImageUploader::STORAGE_TYPE, $filename);
                $newAbsolute = public_path($newRelative);

                if (is_file($oldAbsolute)) {
                    File::ensureDirectoryExists(dirname($newAbsolute), 0755, true);
                    if (! @rename($oldAbsolute, $newAbsolute)) {
                        @copy($oldAbsolute, $newAbsolute);
                        @unlink($oldAbsolute);
                    }
                }

                $updates[$column] = $newRelative;
            }

            if ($updates !== []) {
                DB::table('user_cars')->where('id', $row->id)->update($updates);
            }
        }

        $legacyDir = public_path('user_cars');
        if (is_dir($legacyDir)) {
            @rmdir($legacyDir);
        }
    }

    public function down(): void
    {
        // Geri alınmaz — dosyalar verification altında kalır.
    }
};
