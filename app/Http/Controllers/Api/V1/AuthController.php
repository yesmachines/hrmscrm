<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\SalesCrm\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

class AuthController extends Controller
{
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

        $deviceName = $request->string('device_name')->toString();
        $deviceName = $deviceName !== '' ? $deviceName : 'mobile';

        $token = $user->createToken($deviceName)->plainTextToken;

        $user->load('employee');

        $data = clone $user;
        $data->access_token = $token;

        return $this->successResponse($data);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load('employee');

        return $this->successResponse(new UserResource($user));
    }

    public function logout(Request $request): JsonResponse
    {
        $accessToken = $request->user()?->currentAccessToken();

        if ($accessToken instanceof SanctumToken) {
            $accessToken->delete();
        }

        return $this->successResponse(null, __('Logged out successfully.'));
    }
}
