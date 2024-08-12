<?php

declare(strict_types=1);
//Credits to https://github.com/bootstrapguru/dexor

namespace UseTheFork\Synapse\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Thread extends Model
{
    use HasFactory;

    protected $fillable = [
        'assistant_id',
        'title',
    ];

    public function assistant()
    {
        return $this->belongsTo(Assistant::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}