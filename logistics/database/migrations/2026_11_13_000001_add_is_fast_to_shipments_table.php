<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fast İlan: bugün için ve önümüzdeki 5 saat içinde kalkacak şekilde açılan ilan.
 * İlan oluşturulurken hesaplanır; kartlarda mavi şeritle gösterilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('shipments', 'is_fast')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->boolean('is_fast')->default(false)->after('part_load');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shipments', 'is_fast')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->dropColumn('is_fast');
            });
        }
    }
};
