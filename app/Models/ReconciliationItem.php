<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationItem extends Model
{
    protected $fillable = ['reconciliation_id', 'source_row_id', 'role'];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(Reconciliation::class);
    }

    public function sourceRow(): BelongsTo
    {
        return $this->belongsTo(SourceRow::class);
    }
}
