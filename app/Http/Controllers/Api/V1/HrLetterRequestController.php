<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ApproveLetterRequestRequest;
use App\Http\Requests\Api\V1\RejectLetterRequestRequest;
use App\Http\Resources\Api\V1\HrLetterRequestResource;
use App\Services\LetterRequestService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class HrLetterRequestController extends Controller
{
    use ApiResponse;

    #[OA\Get(
        path: '/api/v1/hr/documents/letters',
        summary: 'List employee letter requests for HR review',
        security: [['sanctum' => []]],
        tags: ['Documents - Letters'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Letter requests retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(Request $request, LetterRequestService $letterRequestService): JsonResponse
    {
        $letters = $letterRequestService->listForHr($request)
            ->through(fn ($letterRequest) => HrLetterRequestResource::make($letterRequest)->resolve($request));

        return $this->successPaginatedResponse($letters, 'letters', 'Letter requests retrieved successfully.');
    }

    #[OA\Get(
        path: '/api/v1/hr/documents/letters/{id}',
        summary: 'Get an employee letter request for HR review',
        security: [['sanctum' => []]],
        tags: ['Documents - Letters'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Letter request details retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Letter request not found'),
        ]
    )]
    public function show(int $id, Request $request, LetterRequestService $letterRequestService): JsonResponse
    {
        $letterRequest = $letterRequestService->findForHr($id);
        $data = HrLetterRequestResource::make($letterRequest)->resolve($request);
        $data['rendered_html'] = $letterRequestService->renderDocumentHtml(
            $letterRequest,
            $letterRequest->employee,
        );
        $fieldRequires = $letterRequest->documentType?->field_requires;
        $data['field_requires'] = empty($fieldRequires) ? null : (object) $fieldRequires;

        return $this->successResponse($data, 'Letter request details retrieved successfully.');
    }

    #[OA\Post(
        path: '/api/v1/hr/documents/letters/{id}/approve',
        summary: 'Approve an employee letter request and generate its letter',
        security: [['sanctum' => []]],
        tags: ['Documents - Letters'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'salary_amount', type: 'string', nullable: true),
                    new OA\Property(property: 'bank_iban', type: 'string', nullable: true),
                    new OA\Property(property: 'to_address', type: 'string', nullable: true),
                    new OA\Property(property: 'destination_country', type: 'string', nullable: true),
                    new OA\Property(property: 'purpose', type: 'string', nullable: true),
                    new OA\Property(property: 'remarks', type: 'string', nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Letter request approved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Letter request not found'),
            new OA\Response(response: 409, description: 'Letter request is no longer pending'),
        ]
    )]
    public function approve(
        ApproveLetterRequestRequest $request,
        int $id,
        LetterRequestService $letterRequestService,
    ): JsonResponse {
        $letterRequest = $letterRequestService->findForHr($id);
        $letterRequestService->approve(
            $letterRequest,
            $request->validated(),
            (int) $request->user()->id,
        );

        $letterRequest = $letterRequestService->findForHr($id);

        return $this->successResponse(
            HrLetterRequestResource::make($letterRequest),
            'Letter request approved successfully.',
        );
    }

    #[OA\Post(
        path: '/api/v1/hr/documents/letters/{id}/reject',
        summary: 'Reject an employee letter request',
        security: [['sanctum' => []]],
        tags: ['Documents - Letters'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['remarks'],
                properties: [
                    new OA\Property(property: 'remarks', type: 'string', maxLength: 500),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Letter request rejected successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Letter request not found'),
            new OA\Response(response: 409, description: 'Letter request is no longer pending'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
    public function reject(
        RejectLetterRequestRequest $request,
        int $id,
        LetterRequestService $letterRequestService,
    ): JsonResponse {
        $letterRequest = $letterRequestService->findForHr($id);
        $letterRequestService->reject(
            $letterRequest,
            $request->validated('remarks'),
            (int) $request->user()->id,
        );

        return $this->successResponse(
            HrLetterRequestResource::make($letterRequestService->findForHr($id)),
            'Letter request rejected successfully.',
        );
    }
}
