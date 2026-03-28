<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'call_id',
        'call_message_id',
        'event_type',
        'title',
        'description',
        'metadata',
        'happened_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'happened_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function callMessage(): BelongsTo
    {
        return $this->belongsTo(CallMessage::class);
    }
}
