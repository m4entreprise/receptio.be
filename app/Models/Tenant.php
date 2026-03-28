<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'locale',
        'timezone',
    ];

    public function agentConfig(): HasOne
    {
        return $this->hasOne(AgentConfig::class);
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }

    public function callMessages(): HasMany
    {
        return $this->hasMany(CallMessage::class);
    }

    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(PhoneNumber::class);
    }

    public function primaryPhoneNumber(): HasOne
    {
        return $this->hasOne(PhoneNumber::class)->where('is_primary', true);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
