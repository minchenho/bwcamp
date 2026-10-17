<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermissionTemplate extends Model
{
    protected $fillable = ['name', 'description', 'camp_table', 'created_by'];

    public $resourceNameInMandarin = '權限範本';

    public $resourceDescriptionInMandarin = '管理可重複套用到職務的權限範本(新增/查詢/修改/刪除)。範本只記錄權限內容，套用時才依職務的營隊與梯次產生實際權限。';

    public function items(): HasMany
    {
        return $this->hasMany(PermissionTemplateItem::class, 'template_id');
    }
}
