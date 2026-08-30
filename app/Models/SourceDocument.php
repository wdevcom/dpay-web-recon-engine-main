<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceDocument extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PARSING = 'parsing';
    public const STATUS_PARSED = 'parsed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'provider_id', 'format', 'filename', 'storage_path', 'file_hash',
        'period_from', 'period_to', 'uploaded_by', 'received_via', 'received_at',
        'parse_status', 'parse_errors', 'rows_count',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'received_at' => 'datetime',
        'parse_errors' => 'array',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(SourceRow::class);
    }
}
