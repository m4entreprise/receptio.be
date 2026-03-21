<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'call_id',
        'caller_name',
        'caller_number',
        'message_text',
        'recording_url',
        'recording_duration',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
        ];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
