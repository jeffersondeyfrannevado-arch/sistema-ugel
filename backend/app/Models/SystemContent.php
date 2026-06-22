<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemContent extends Model
{
    protected $fillable = [
        'title',
        'body',
        'type',
        'status',
        'is_flagged',
        'flagged_reason',
        'review_notes',
        'created_by',
        'updated_by',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'is_flagged' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
