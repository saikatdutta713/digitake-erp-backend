<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * Return a standardized successful JSON response.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function success(mixed $data = null, string $message = 'Success', int $statusCode = 200, array $meta = []): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        if (! empty($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $statusCode);
    }

    /**
     * Return a standardized error JSON response.
     *
     * @param  array<string, mixed>|null  $details
     */
    public static function error(string $code, string $message, int $statusCode = 400, ?array $details = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ($details !== null) {
            $payload['error']['details'] = $details;
        }

        return response()->json($payload, $statusCode);
    }
}
