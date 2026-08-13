<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponse
{
    /**
     * Build a success response
     *
     * @param  mixed  $data
     * @param  string  $message
     * @param  int  $code
     * @return JsonResponse
     */
    public function successResponse($data = null, $message = 'Success', $code = 200)
    {
        $response = [
            'statusCode' => $code,
            'message' => $message,
        ];

        if (! is_null($data)) {
            $response['data'] = $data;
        } else {
            $response['data'] = [];
        }

        return response()->json($response, $code);
    }

    /**
     * Build an error response
     *
     * @param  string  $message
     * @param  int  $code
     * @param  mixed  $data
     * @return JsonResponse
     */
    public function errorResponse($message = 'Error', $code = 400, $data = null)
    {
        $response = [
            'statusCode' => $code,
            'message' => $message,
        ];

        if (! is_null($data)) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Build a success response with pagination
     *
     * @param  LengthAwarePaginator  $paginator
     * @param  string  $itemsKey
     * @param  string  $message
     * @param  int  $code
     * @return JsonResponse
     */
    public function successPaginatedResponse($paginator, $itemsKey = 'items', $message = 'Success', $code = 200)
    {
        $response = [
            'statusCode' => $code,
            'message' => $message,
            'data' => [
                $itemsKey => $paginator->items(),
                'pagination' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ];

        return response()->json($response, $code);
    }
}
