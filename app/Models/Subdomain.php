<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subdomain extends Model
{
    //
    
    protected $fillable = [
        'project_id',
        'subdomain_name',
        'folder_path',
    ];
}
