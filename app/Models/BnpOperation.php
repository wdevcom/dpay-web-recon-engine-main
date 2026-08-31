<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Dziennik wywołań GOconnect. Wpis powstaje ZANIM komunikat pójdzie do
 * banku - przy zleceniach `message_id` jest jedynym kluczem, którym da się
 * potem ustalić, czy bank przyjął dyspozycję mimo braku odpowiedzi.
 */
class BnpOperation extends Model
{
    public const STATUS_SENT = 'sent';
    public const STATUS_OK = 'ok';
    public const STATUS_FAILED = 'failed';
    public const STATUS_UNCERTAIN = 'uncertain';

    protected $fillable = [
        'operation', 'message_id', 'account_iban', 'status', 'duration_ms',
        'error_class', 'error_message', 'context',
    ];

    protected $casts = ['context' => 'array'];
}
