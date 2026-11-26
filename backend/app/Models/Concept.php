<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Concept extends Model
{
    protected $fillable = ['position', 'title', 'summary', 'source_quote'];

    public function lecture(): BelongsTo
    {
        return $this->belongsTo(Lecture::class);
    }


    public function explanations(): HasMany
{
    return $this->hasMany(Explanation::class);
}
}