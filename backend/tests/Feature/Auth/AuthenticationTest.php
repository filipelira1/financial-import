<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'secret-password',
    ]);
});

describe('login', function () {
    it('issues a bearer token for valid credentials', function () {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'secret-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'jane@example.com')
            ->assertJsonStructure(['data' => ['token', 'token_type', 'expires_at', 'user' => ['id', 'name', 'email']]]);

        expect(PersonalAccessToken::count())->toBe(1);

        $this->withToken($response->json('data.token'))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $this->user->id);
    });

    it('rejects a wrong password and an unknown e-mail with the same message', function (string $email) {
        $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => __('auth.failed')]);

        expect(PersonalAccessToken::count())->toBe(0);
    })->with(['jane@example.com', 'nobody@example.com']);

    it('validates the payload', function () {
        $this->postJson('/api/v1/auth/login', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    });

    it('throttles repeated attempts', function () {
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/auth/login', ['email' => 'jane@example.com', 'password' => 'wrong-password'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'jane@example.com', 'password' => 'secret-password'])
            ->assertTooManyRequests();
    });
});

describe('protected routes', function () {
    it('returns 401 without a token, even when the client does not ask for JSON', function () {
        $this->get('/api/v1/auth/me')->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
    });

    it('returns 401 for an invalid token', function () {
        $this->withToken('1|invalid')->getJson('/api/v1/auth/me')->assertUnauthorized();
    });

    it('returns 401 once the token has expired', function () {
        $token = $this->user->createToken('api', ['*'], now()->addMinutes(10))->plainTextToken;

        $this->travel(11)->minutes();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    });
});

describe('logout', function () {
    it('revokes only the token used in the request', function () {
        $current = $this->user->createToken('browser')->plainTextToken;
        $this->user->createToken('other-device');

        $this->withToken($current)->postJson('/api/v1/auth/logout')->assertNoContent();

        expect(PersonalAccessToken::pluck('name')->all())->toBe(['other-device']);

        // The guard caches the user for the lifetime of the test application.
        $this->app['auth']->forgetGuards();

        $this->withToken($current)->getJson('/api/v1/auth/me')->assertUnauthorized();
    });
});
