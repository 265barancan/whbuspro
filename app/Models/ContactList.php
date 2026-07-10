<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactList extends Model
{
    protected $fillable = ['name'];

    /**
     * Listedeki kişiler.
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_list_members', 'list_id', 'contact_id');
    }

    /**
     * Bu listeye atanan kampanyalar.
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'list_id');
    }
}
