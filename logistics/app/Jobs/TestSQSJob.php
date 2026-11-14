<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class TestSQSJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        Log::info('TestSQSJob dispatched daw');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //
    }
}
