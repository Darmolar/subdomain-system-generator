<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    //
    protected $fillable = [
        'project_id',
        'domain_name',
        'folder_path',
    ];
}
