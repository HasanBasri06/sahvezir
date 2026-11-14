<?php

namespace App\Jobs;

use App\Models\Shipment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CloseShipmentJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $shipmentId)
    {
        
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $shipment = Shipment::find($this->shipmentId);
        if (!$shipment) {
            Log::error('Shipment not found: ' . $this->shipmentId);
            return;
        }
        
        $shipment->update(['status' => 'inactive']);
    }
}
