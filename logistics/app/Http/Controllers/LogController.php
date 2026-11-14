<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogController extends Controller
{
    public function callRequest(Request $request)
    {
        $user = $request->user();
        $slug = $request->slug;
        $shipment = Shipment::where('slug', $slug)->first();

        $message = sprintf(
            "\n\n------------------------------------------------------------------------\n\nKullanıcı #%s %s İlanı aldı.\nİlan bilgisi: #%d %s / %s -> %s / %s\n\n------------------------------------------------------------------------",
            $user->first_name,
            $user->last_name,
            $shipment->id,
            $shipment->f_where_city,
            $shipment->f_where_district,
            $shipment->t_where_city,
            $shipment->t_where_district
        );

        Log::info($message);
    }
}
