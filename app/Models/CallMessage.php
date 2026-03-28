<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallMessage extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_CALLED_BACK = 'called_back';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'tenant_id',
        'call_id',
        'status',
        'caller_name',
        'caller_number',
        'message_text',
        'recording_url',
        'recording_duration',
        'notified_at',
        'assigned_to_user_id',
        'handled_by_user_id',
        'handled_at',
    ];

    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
            'handled_at' => 'datetime',
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

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_user_id');
    }

    public static function workflowStatuses(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_IN_PROGRESS,
            self::STATUS_CALLED_BACK,
            self::STATUS_CLOSED,
        ];
    }
}
