<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lecture extends Model
{
    protected $fillable = ['title', 'content'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }


    public function concepts(): HasMany
{
    return $this->hasMany(Concept::class)->orderBy('position');
}
}