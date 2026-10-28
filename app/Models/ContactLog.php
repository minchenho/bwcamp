<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContactLog extends Model
{
    //
    protected $table = 'contact_log';

    public $resourceNameInMandarin = '關懷記錄';

    public $resourceDescriptionInMandarin = '針對不同職別的義工，在使用學員及義工的關懷記錄上，提供不指定/義工大組／小組/個人不同的權限範疇。針對關懷記錄提供新增/查詢/修改/刪除的功能。';

    protected $fillable = [
        'applicant_id', 'user_id', 'notes'
    ];

    public function applicant()
    {
        return $this->belongsTo('App\Models\Applicant');
    }

    //user: by whom the log is taken
    public function user(): BelongsTo
    {
        return $this->belongsTo('App\Models\User');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ContactLogTag::class, 'contact_log_tag_xref', 'log_id', 'tag_id')->withTimestamps();
    }
}
