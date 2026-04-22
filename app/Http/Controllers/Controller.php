<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

abstract class Controller
{
    /**
     * Standard Success Response
     */
    protected function success(mixed $data = null, string $message = 'Success', int $code = 200, mixed $meta = null): JsonResponse
    {
        // Jika data adalah Laravel Resource, kita ambil array-nya
        // Ini menjaga konsistensi jika kamu pakai API Resource
        if ($data instanceof JsonResource || $data instanceof ResourceCollection) {
            $data = $data->response()->getData(true);

            // Jika Resource sudah punya metadata bawaan, kita gabung
            if (isset($data['meta'])) {
                $meta = array_merge((array)$meta, $data['meta']);
                $data = $data['data'];
            }
        }

        $response = [
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ];

        if ($meta !== null) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $code);
    }

    /**
     * Standard Error Response
     */
    protected function error(string $message = 'Error', mixed $errors = [], int $code = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors ?? (object)[], // Kembalikan objek kosong jika null agar JS-friendly
        ], $code);
    }

    /**
     * Helper: 201 Created
     */
    protected function created(mixed $data = null, string $message = 'Resource created successfully'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    /**
     * Helper: 204 No Content (Biasanya untuk DELETE)
     */
    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Helper: 401 Unauthorized
     */
    protected function unauthorized(string $message = 'Unauthenticated'): JsonResponse
    {
        return $this->error($message, null, 401);
    }

    /**
     * Helper: 403 Forbidden
     */
    protected function forbidden(string $message = 'This action is unauthorized'): JsonResponse
    {
        return $this->error($message, null, 403);
    }

    /**
     * Helper: 404 Not Found
     */
    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, null, 404);
    }
}
