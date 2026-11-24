<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Auth\IssueApiTokenAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\AccessTokenResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    /**
     * Exchange e-mail and password for a Bearer token.
     *
     * @unauthenticated
     */
    public function login(LoginRequest $request, IssueApiTokenAction $issueToken): AccessTokenResource
    {
        $token = $issueToken->execute(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('device_name', 'api'),
        );

        return new AccessTokenResource($token);
    }

    /**
     * Revoke the token used in this request.
     */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * The authenticated user.
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
