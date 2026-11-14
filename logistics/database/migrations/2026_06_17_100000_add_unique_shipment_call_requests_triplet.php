<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateGroups = DB::table('shipment_call_requests')
            ->select('shipment_id', 'vehicle_owner_id', 'cargo_owner_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('shipment_id', 'vehicle_owner_id', 'cargo_owner_id')
            ->having('cnt', '>', 1)
            ->get();

        foreach ($duplicateGroups as $group) {
            $keepId = DB::table('shipment_call_requests')
                ->where('shipment_id', $group->shipment_id)
                ->where('vehicle_owner_id', $group->vehicle_owner_id)
                ->where('cargo_owner_id', $group->cargo_owner_id)
                ->orderByDesc('called_at')
                ->orderByDesc('id')
                ->value('id');

            if (! $keepId) {
                continue;
            }

            DB::table('shipment_call_requests')
                ->where('shipment_id', $group->shipment_id)
                ->where('vehicle_owner_id', $group->vehicle_owner_id)
                ->where('cargo_owner_id', $group->cargo_owner_id)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        Schema::table('shipment_call_requests', function (Blueprint $table) {
            $table->unique(
                ['shipment_id', 'vehicle_owner_id', 'cargo_owner_id'],
                'shipment_call_requests_shipment_vehicle_cargo_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('shipment_call_requests', function (Blueprint $table) {
            $table->dropUnique('shipment_call_requests_shipment_vehicle_cargo_unique');
        });
    }
};
