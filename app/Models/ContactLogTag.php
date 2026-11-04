<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 關懷記錄標籤：依營隊類型（camp_table）共用，由營隊管理者在營隊工具箱設定
 */
class ContactLogTag extends Model
{
    protected $table = 'contact_log_tag';

    // 標籤清單改由營隊工具箱管理，不列入權限矩陣（getAvailableModels 會略過名稱為空的 Model）
    public $resourceNameInMandarin = '';
    public $resourceDescriptionInMandarin = '';

    protected $fillable = [
        'camp_table', 'tag', 'notes'
    ];

    public function logs(): BelongsToMany
    {
        return $this->belongsToMany(ContactLog::class, 'contact_log_tag_xref', 'tag_id', 'log_id')->withTimestamps();
    }

    /**
     * 某營隊類型可用的標籤
     */
    public function scopeForCampTable($query, string $campTable)
    {
        return $query->where('camp_table', $campTable)->orderBy('tag');
    }
}
