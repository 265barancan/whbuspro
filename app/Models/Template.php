<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    protected $fillable = [
        'meta_template_name',
        'language_code',
        'category',
        'status',
        'body_variables_count',
    ];

    /**
     * Bu şablonu kullanan kampanyalar.
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }
}
