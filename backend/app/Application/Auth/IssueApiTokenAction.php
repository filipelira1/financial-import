<?php

namespace App\Application\Auth;

use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use SensitiveParameter;

final readonly class IssueApiTokenAction
{
    public function __construct(private AuthFactory $auth) {}

    /**
     * @throws ValidationException when the credentials do not match a user
     */
    public function execute(string $email, #[SensitiveParameter] string $password, string $deviceName): NewAccessToken
    {
        /** @var SessionGuard $guard */
        $guard = $this->auth->guard('web');

        // validate() runs inside a timebox: an unknown e-mail and a wrong password
        // take the same time, so the response does not reveal which accounts exist.
        if (! $guard->validate(['email' => $email, 'password' => $password])) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        /** @var User $user */
        $user = $guard->getLastAttempted();

        $ttl = config('sanctum.expiration');

        $token = $user->createToken($deviceName, ['*'], $ttl ? now()->addMinutes((int) $ttl) : null);
        $token->accessToken->setRelation('tokenable', $user);

        return $token;
    }
}
