<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Konsument mikroserwisu: dpay-web-manager, dpay-web-eid, dpay-web-esim. */
class Tenant extends Model
{
    protected $fillable = ['code', 'name', 'webhook_url', 'webhook_secret', 'is_active'];

    protected $casts = [
        'is_active'      => 'bool',
        'webhook_secret' => 'encrypted',
    ];

    public function apiKeys(): HasMany
    {
        return $this->hasMany(TenantApiKey::class);
    }

    public function virtualAccounts(): HasMany
    {
        return $this->hasMany(VirtualAccount::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(MasscollectDomain::class);
    }
}
