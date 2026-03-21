<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'agent_name',
        'welcome_message',
        'after_hours_message',
        'faq_content',
        'transfer_phone_number',
        'notification_email',
        'opens_at',
        'closes_at',
        'business_days',
    ];

    protected function casts(): array
    {
        return [
            'business_days' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
