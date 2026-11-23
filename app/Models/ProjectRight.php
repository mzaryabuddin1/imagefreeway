<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectRight extends Model
{
    public const RIGHTS = [
        'can_view' => 'View',
        'can_download' => 'Download',
        'can_upload' => 'Upload',
        'can_index' => 'Index',
        'can_delete' => 'Delete',
    ];

    protected $fillable = ['project_id', 'user_id', 'can_view', 'can_download', 'can_upload', 'can_index', 'can_delete'];

    protected function casts(): array
    {
        return array_fill_keys(array_keys(self::RIGHTS), 'boolean');
    }
}
