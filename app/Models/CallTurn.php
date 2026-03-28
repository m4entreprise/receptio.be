<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallTurn extends Model
{
    use HasFactory;

    protected $fillable = [
        'call_id',
        'speaker',
        'text',
        'confidence',
        'sequence',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'meta' => 'array',
        ];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }
}
