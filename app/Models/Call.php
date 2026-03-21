<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Call extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'phone_number_id',
        'external_sid',
        'direction',
        'status',
        'from_number',
        'to_number',
        'started_at',
        'ended_at',
        'transcript',
        'summary',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function message(): HasOne
    {
        return $this->hasOne(CallMessage::class);
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(PhoneNumber::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
