<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\EmployeeProfile;
use App\Models\SalesCrm\Division;
use App\Models\SalesCrm\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken as SanctumToken;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/v1/login',
        summary: 'User login',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                    new OA\Property(property: 'device_name', type: 'string', example: 'mobile'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful login',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'access_token', type: 'string'),
                        new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                        new OA\Property(
                            property: 'user',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'email', type: 'string'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $email = Str::lower($request->string('email')->toString());

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('These credentials do not match our records.')],
            ]);
        }

        $user->load('employee');

        if ($user->employee === null) {
            throw ValidationException::withMessages([
                'email' => [__('These credentials do not match our records.')],
            ]);
        }

        if ($user->employee->status !== 1) {
            throw ValidationException::withMessages([
                'email' => [__('Your employee account is inactive.')],
            ]);
        }

        $deviceName = $request->string('device_name')->toString();
        $deviceName = $deviceName !== '' ? $deviceName : 'mobile';

        $token = $user->createToken($deviceName)->plainTextToken;

        $data = clone $user;
        $data->access_token = $token;
        $data->token = $token;
        $data->token_type = 'Bearer';

        $role = null;
        if (method_exists($user, 'roleNames')) {
            $names = $user->roleNames();
            $role = is_array($names) && count($names) ? $names[0] : null;
        }

        $data->user = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $role,
        ];

        return $this->successResponse($data);
    }

    #[OA\Get(
        path: '/api/v1/me',
        summary: 'Get authenticated user details',
        security: [['sanctum' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User details, including all employee and employee profile fields shown in the admin profile',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'statusCode', type: 'integer'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'email', type: 'string'),
                                new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                                new OA\Property(
                                    property: 'employee',
                                    type: 'object',
                                    description: 'Complete employment details and personal, contact, visa, and education profile fields; null when no employee record exists'
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load([
            'employee.user:id,name,email',
            'employee.department:id,name',
            'employee.organisation:id,org_name,short_name',
            'employee.officeLocation:id,office_name,city',
        ]);

        if ($user->employee !== null) {
            $employee = $user->employee;
            $profile = EmployeeProfile::query()
                ->where('employee_id', $employee->id)
                ->first();

            $employee->setAttribute(
                'division_name',
                Division::query()
                    ->whereRaw('LOWER(code) = ?', [strtolower($employee->division)])
                    ->value('name'),
            );
            $employee->setRelation('profileDetails', $profile);

            if ($profile !== null) {
                $countryIds = array_filter([
                    is_numeric($profile->nationality) ? (int) $profile->nationality : null,
                    $profile->home_country,
                ]);
                $countries = $countryIds === []
                    ? collect()
                    : DB::connection('salescrm')
                        ->table('countries')
                        ->whereIn('id', $countryIds)
                        ->pluck('name', 'id');

                if (is_numeric($profile->nationality)) {
                    $profile->setAttribute(
                        'nationality',
                        $countries[(int) $profile->nationality] ?? $profile->nationality,
                    );
                }

                $profile->setAttribute(
                    'home_country_name',
                    $profile->home_country ? ($countries[$profile->home_country] ?? null) : null,
                );
            }
        }

        return $this->successResponse(new UserResource($user));
    }

    #[OA\Post(
        path: '/api/v1/logout',
        summary: 'User logout',
        security: [['sanctum' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(response: 200, description: 'Logged out successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $accessToken = $request->user()?->currentAccessToken();

        if ($accessToken instanceof SanctumToken) {
            $accessToken->delete();
        }

        return $this->successResponse(null, __('Logged out successfully.'));
    }
}
