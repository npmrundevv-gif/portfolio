<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimelineEvent extends Model
{
    protected $fillable = [
        'section_id',
        'year',
        'title',
        'description',
        'tags',
        'image_path',
        'url',
        'sort_order',
        'enabled',
    ];

    protected $casts = [
        'tags' => 'array',
        'enabled' => 'boolean',
    ];

    public function section()
    {
        return $this->belongsTo(Section::class);
    }
}
