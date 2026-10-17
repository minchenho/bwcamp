<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermissionTemplateItem extends Model
{
    protected $fillable = ['template_id', 'resource', 'action', 'range'];

    // 不列入權限矩陣（getAvailableModels 會略過名稱為空的 Model）
    public $resourceNameInMandarin = '';

    public $resourceDescriptionInMandarin = '';

    public function template(): BelongsTo
    {
        return $this->belongsTo(PermissionTemplate::class, 'template_id');
    }
}
