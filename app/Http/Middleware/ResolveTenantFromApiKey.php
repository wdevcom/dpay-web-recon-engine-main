<?php

namespace App\Http\Middleware;

use App\Models\TenantApiKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Uwierzytelnienie konsumenta kluczem API.
 *
 * Klucz przyjmujemy z `Authorization: Bearer ...` albo z `X-Api-Key`.
 * W bazie leży wyłącznie hash, więc wyszukujemy po nim - nie ma sposobu,
 * żeby odtworzyć klucz z zawartości bazy.
 */
class ResolveTenantFromApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $plain = $this->extractKey($request);

        if ($plain === null) {
            return $this->unauthorized('Brak klucza API.');
        }

        $key = TenantApiKey::with('tenant')
            ->where('key_hash', TenantApiKey::hash($plain))
            ->first();

        if ($key === null || ! $key->isUsable()) {
            return $this->unauthorized('Klucz API jest nieznany, wygasł lub został odwołany.');
        }

        if ($key->tenant === null || ! $key->tenant->is_active) {
            return $this->unauthorized('Konsument jest nieaktywny.');
        }

        // Znacznik ostatniego użycia bez dotykania updated_at - to tylko
        // telemetria, nie zmiana stanu klucza.
        $key->forceFill(['last_used_at' => now()])->saveQuietly();

        $request->attributes->set('tenant', $key->tenant);
        $request->attributes->set('api_key', $key);

        return $next($request);
    }

    private function extractKey(Request $request): ?string
    {
        $bearer = $request->bearerToken();

        if (is_string($bearer) && $bearer !== '') {
            return $bearer;
        }

        $header = $request->header('X-Api-Key');

        return is_string($header) && $header !== '' ? $header : null;
    }

    private function unauthorized(string $message): JsonResponse
    {
        return new JsonResponse(['message' => $message], 401);
    }
}
