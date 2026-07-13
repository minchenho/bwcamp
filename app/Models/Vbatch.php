<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

// 直接繼承 Batch，所有星期幾運算、智慧欄位通通無痛擁有！
class Vbatch extends Batch
{
    protected $table = 'batches';

    public $resourceNameInMandarin = '義工梯次資料';

    public $resourceDescriptionInMandarin = '在義工營隊裡的皆是義工梯次，它和主營隊的梯次理論上是一對一';

    /**
     * 🛡️ 全域作用域：確保 Vbatch::all() 只會撈出義工營隊的梯次
     */
    protected static function booted()
    {
        static::addGlobalScope('is_vbatch', function (Builder $builder) {
            $builder->whereHas('camp', function ($query) {
                $query->where('table', 'like', '%vcamp%');
            });
        });
    }

    // 這裡空空如也，因為父類別 Batch 已經幫你罩著一切了！
}