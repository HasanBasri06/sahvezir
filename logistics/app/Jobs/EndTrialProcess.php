<?php

namespace App\Jobs;

use App\Mail\EndTrialAccountMail;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class EndTrialProcess implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public User $user)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        app(\App\Services\SubscriptionService::class)->expire($this->user);
        Mail::to($this->user->email)->send(new EndTrialAccountMail($this->user));
    }
}
