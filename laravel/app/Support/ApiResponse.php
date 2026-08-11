<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiResponse
{
    public static function paginated(LengthAwarePaginator $paginator): JsonResponse
    {
        $paginator->appends(request()->except('page'));

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    public static function error(Request $request, string $code, string $message, int $status, array $details = []): JsonResponse
    {
        $payload = [
            // Kept at the top level for backwards compatibility with existing clients.
            'message' => $message,
            'code' => $code,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details === [] ? null : $details,
            ],
            'meta' => ['request_id' => $request->attributes->get('waflow.request_id')],
        ];

        if ($details !== []) {
            // Laravel clients use this field for assertJsonValidationErrors and form errors.
            $payload['errors'] = $details;
        }

        return response()->json($payload, $status);
    }

    public static function perPage(Request $request, int $default = 25): int
    {
        return max(1, min($request->integer('per_page', $default), 100));
    }
}
