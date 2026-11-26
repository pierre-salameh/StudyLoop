<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Explanation extends Model
{
    protected $fillable = ['style', 'explanation', 'example', 'status', 'attempts', 'log'];
    protected $hidden = ['log'];

    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }
}