<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Access\Actions\AuthenticateAction;
use App\Domain\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

class AuthController extends Controller
{
    /**
     * Log in and receive a JWT.
     *
     * Five failed attempts per email + IP lock the login for a minute (429).
     *
     * @unauthenticated
     */
    public function login(LoginRequest $request, AuthenticateAction $authenticate): JsonResponse
    {
        $user = $authenticate->handle($request, 'api');

        return $this->respondWithToken($this->guard()->login($user));
    }

    /** The current user with roles and effective permissions. */
    public function me(): UserResource
    {
        /** @var User $user */
        $user = $this->guard()->user();

        return (new UserResource($user))->withPermissions();
    }

    public function logout(AuditLogger $audit): JsonResponse
    {
        $audit->log('auth.logout', null, ['guard' => 'api']);
        $this->guard()->logout();

        return response()->json(['message' => 'Successfully logged out.']);
    }

    public function refresh(): JsonResponse
    {
        return $this->respondWithToken($this->guard()->refresh());
    }

    private function respondWithToken(string $token): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $this->guard()->factory()->getTTL() * 60,
        ]);
    }

    private function guard(): JWTGuard
    {
        /** @var JWTGuard */
        return Auth::guard('api');
    }
}
