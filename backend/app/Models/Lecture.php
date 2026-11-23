<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lecture extends Model
{
    protected $fillable = ['title', 'content'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}