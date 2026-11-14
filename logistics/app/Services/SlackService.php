<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SlackService
{
    /** Slack attachment renkleri */
    public const COLOR_SUCCESS = '#2eb886';

    public const COLOR_DANGER = '#e01e5a';

    public function sendMessage(string $message, string $channel, ?string $attachmentColor = null): void
    {
        if ($channel === '') {
            return;
        }

        $payload = $attachmentColor !== null
            ? [
                'attachments' => [
                    [
                        'color' => $attachmentColor,
                        'text' => $message,
                        'mrkdwn_in' => ['text'],
                    ],
                ],
            ]
            : ['text' => $message];

        Http::post($channel, $payload);
    }
}
