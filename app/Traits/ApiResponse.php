<?php

namespace App\Traits;

trait ApiResponse
{
    /**
     * Build a success response
     *
     * @param  mixed  $data
     * @param  string  $message
     * @param  int  $code
     * @return \Illuminate\Http\JsonResponse
     */
    public function successResponse($data = null, $message = 'Success', $code = 200)
    {
        $response = [
            'status' => $code,
            'message' => $message,
        ];

        if (!is_null($data)) {
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
     * @return \Illuminate\Http\JsonResponse
     */
    public function errorResponse($message = 'Error', $code = 400, $data = null)
    {
        $response = [
            'status' => $code,
            'message' => $message,
        ];

        if (!is_null($data)) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }
}
