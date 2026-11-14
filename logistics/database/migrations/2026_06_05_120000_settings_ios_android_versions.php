<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        if (Schema::hasColumn('settings', 'ios_version') && Schema::hasColumn('settings', 'android_version')) {
            return;
        }

        Schema::table('settings', function (Blueprint $table) {
            if (! Schema::hasColumn('settings', 'ios_version')) {
                $table->string('ios_version', 32)->nullable()->after('id');
            }
            if (! Schema::hasColumn('settings', 'android_version')) {
                $table->string('android_version', 32)->nullable()->after('ios_version');
            }
        });

        if (Schema::hasColumn('settings', 'version')) {
            foreach (DB::table('settings')->orderBy('id')->get() as $row) {
                $legacy = trim((string) ($row->version ?? ''));
                if ($legacy === '') {
                    $legacy = '1.0.0';
                }
                DB::table('settings')->where('id', $row->id)->update([
                    'ios_version' => $row->ios_version ?: $legacy,
                    'android_version' => $row->android_version ?: $legacy,
                ]);
            }

            Schema::table('settings', function (Blueprint $table) {
                $table->dropColumn('version');
            });
        }

        DB::table('settings')->whereNull('ios_version')->update(['ios_version' => '1.0.0']);
        DB::table('settings')->whereNull('android_version')->update(['android_version' => '1.0.0']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        if (! Schema::hasColumn('settings', 'version')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->string('version', 32)->default('1.0.0')->after('id');
            });

            foreach (DB::table('settings')->orderBy('id')->get() as $row) {
                $v = $row->android_version ?: $row->ios_version ?: '1.0.0';
                DB::table('settings')->where('id', $row->id)->update(['version' => $v]);
            }
        }

        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'ios_version')) {
                $table->dropColumn('ios_version');
            }
            if (Schema::hasColumn('settings', 'android_version')) {
                $table->dropColumn('android_version');
            }
        });
    }
};
