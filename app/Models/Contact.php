<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    protected $fillable = [
        'phone_number',
        'full_name',
        'opted_in',
        'opted_in_at',
        'opted_out_at',
        'status',
    ];

    protected $casts = [
        'opted_in' => 'boolean',
        'opted_in_at' => 'datetime',
        'opted_out_at' => 'datetime',
    ];

    /**
     * Kişinin dahil olduğu listeler.
     */
    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(ContactList::class, 'contact_list_members', 'contact_id', 'list_id');
    }

    /**
     * Kişiye giden mesajlar.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
