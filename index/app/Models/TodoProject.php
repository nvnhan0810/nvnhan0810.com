<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TodoProject extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'git_repo_url',
        'domain',
    ];

    public function todos(): HasMany
    {
        return $this->hasMany(Todo::class, 'project_id');
    }
}
