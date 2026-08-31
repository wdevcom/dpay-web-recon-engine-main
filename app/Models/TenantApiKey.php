<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Klucz API konsumenta. W bazie leży wyłącznie hash - klucz w jawnej
 * postaci pokazujemy raz, w chwili nadania, i nigdy więcej.
 */
class TenantApiKey extends Model
{
    protected $fillable = ['tenant_id', 'name', 'key_hash', 'key_prefix', 'last_used_at', 'expires_at', 'revoked_at'];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at'   => 'datetime',
        'revoked_at'   => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Nadaje nowy klucz i zwraca [model, jawny klucz]. Jawny klucz jest
     * jedynym momentem, w którym da się go odczytać.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(Tenant $tenant, string $name, ?\DateTimeInterface $expiresAt = null): array
    {
        $plain = 'dprk_'.Str::random(48);

        $key = self::create([
            'tenant_id'  => $tenant->id,
            'name'       => $name,
            'key_hash'   => self::hash($plain),
            'key_prefix' => substr($plain, 0, 12),
            'expires_at' => $expiresAt,
        ]);

        return [$key, $plain];
    }

    /**
     * SHA-256, nie bcrypt: klucz jest losowy i długi, więc rozciąganie nic
     * nie wnosi, a przy każdym żądaniu API liczy się czas wyszukania po
     * indeksie, nie odporność na atak słownikowy.
     */
    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
