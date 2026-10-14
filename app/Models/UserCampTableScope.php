<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserCampTableScope extends Model
{
    protected $fillable = ['user_id', 'camp_table'];

    // 不列入權限矩陣（getAvailableModels 會略過名稱為空的 Model）
    public $resourceNameInMandarin = '';

    public $resourceDescriptionInMandarin = '';
}
