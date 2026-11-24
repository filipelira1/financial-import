<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\NewAccessToken;

/** @property NewAccessToken $resource */
class AccessTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->resource->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $this->resource->accessToken->expires_at?->toIso8601String(),
            'user' => new UserResource($this->resource->accessToken->tokenable),
        ];
    }
}
