<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\MessageBag;

trait ResponseTrait
{
    protected function success(mixed $content = [], string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => $message,
            'content' => $content,
            'errors' => [],
        ], $status);
    }

    protected function error(
        mixed $content = [],
        string $message = 'Hata',
        mixed $errors = [],
        int $status = 400
    ): JsonResponse {
        if ($errors instanceof MessageBag) {
            $errors = $errors->toArray();
        }

        return response()->json([
            'status' => false,
            'message' => $message,
            'content' => $content,
            'errors' => $errors ?: [],
        ], $status);
    }
}
