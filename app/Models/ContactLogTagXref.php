<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactLogTagXref extends Model
{
    protected $table = 'contact_log_tag_xref';

    // 由 ContactLog::tags() 維護，不列入權限矩陣
    public $resourceNameInMandarin = '';
    public $resourceDescriptionInMandarin = '';

    protected $fillable = [
        'log_id', 'tag_id'
    ];
}
