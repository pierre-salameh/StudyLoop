<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Concept extends Model
{
    protected $fillable = ['position', 'title', 'summary', 'source_quote'];

    public function lecture(): BelongsTo
    {
        return $this->belongsTo(Lecture::class);
    }
}