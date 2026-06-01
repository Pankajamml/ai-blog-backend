<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Blog extends Model
{
    protected $fillable = [
        'topic',
        'content',
        'platform',
        'status',
        'tone',
        'image_url',
        'scheduled_at',
        'published_at',
        'is_published',
    ];
      protected $casts = [
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'is_published' => 'boolean',
    ];
}