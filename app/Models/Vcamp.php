<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class Vcamp extends Camp
{
    // 1. 覆寫父類別的屬性（因為中文描述與學員營隊不同）
    public $resourceNameInMandarin = '義工營隊資料';
    public $resourceDescriptionInMandarin = '將義工視為一個新的營隊，可以設定義工營隊所有的基本資料(內容與學員營隊一樣)，提供營隊義工報名使用。';

    /**
     * 2. 安全防護：全域作用域 (Global Scope)
     * 確保當使用 Vcamp::all() 或 Vcamp::find() 時，只會抓到資料庫中屬於義工營隊的資料。
     */
    protected static function booted()
    {
        static::addGlobalScope('is_vcamp', function (Builder $builder) {
            $builder->where('table', 'like', '%vcamp%');
        });
    }
}
