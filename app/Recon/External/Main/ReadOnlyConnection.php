<?php

namespace App\Recon\External\Main;

use Illuminate\Database\Eloquent\Model;

/**
 * Trait for any model that lives in `main_db`. Forces the read-only contract:
 * - `$connection` is `main_db`
 * - any save / update / delete attempt throws.
 *
 * The recon engine should never write into the payment-gateway database;
 * this trait is the application-side guardrail (real protection should also
 * be enforced by the DB user's grants).
 */
trait ReadOnlyConnection
{
    public function getConnectionName(): string
    {
        return 'main_db';
    }

    public static function bootReadOnlyConnection(): void
    {
        static::saving(fn () => throw new \DomainException(static::class.' is read-only (main_db).'));
        static::deleting(fn () => throw new \DomainException(static::class.' is read-only (main_db).'));
    }

    public function save(array $options = []): bool
    {
        throw new \DomainException(static::class.' is read-only (main_db).');
    }

    public function delete(): bool|null
    {
        throw new \DomainException(static::class.' is read-only (main_db).');
    }
}
