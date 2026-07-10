<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $fillable = [
        'name',
        'template_id',
        'list_id',
        'status',
        'throttle_per_minute',
        'scheduled_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    /**
     * Kampanyada kullanılan şablon.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * Kampanyanın gönderildiği alıcı listesi.
     */
    public function list(): BelongsTo
    {
        return $this->belongsTo(ContactList::class, 'list_id');
    }

    /**
     * Kampanya kapsamında gönderilen tekil mesajlar.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
