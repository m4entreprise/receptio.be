<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CallMessage extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_CALLED_BACK = 'called_back';

    public const STATUS_CLOSED = 'closed';

    public const TRANSCRIPTION_STATUS_PENDING = 'pending';

    public const TRANSCRIPTION_STATUS_COMPLETED = 'completed';

    public const TRANSCRIPTION_STATUS_UNAVAILABLE = 'unavailable';

    public const TRANSCRIPTION_STATUS_FAILED = 'failed';

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
        'callback_due_at',
        'transcription_status',
        'transcript_provider',
        'transcription_error',
        'transcription_processed_at',
        'ai_summary',
        'ai_intent',
        'urgency_level',
        'automation_processed_at',
    ];

    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
            'handled_at' => 'datetime',
            'callback_due_at' => 'datetime',
            'transcription_processed_at' => 'datetime',
            'automation_processed_at' => 'datetime',
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

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
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

    public static function transcriptionStatuses(): array
    {
        return [
            self::TRANSCRIPTION_STATUS_PENDING,
            self::TRANSCRIPTION_STATUS_COMPLETED,
            self::TRANSCRIPTION_STATUS_UNAVAILABLE,
            self::TRANSCRIPTION_STATUS_FAILED,
        ];
    }
}
