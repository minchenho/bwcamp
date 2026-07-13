<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Class Vcamp
 * * 義工營隊模型
 * 本模型採用單一表繼承 (Single Table Inheritance) 架構，直接繼承自 Camp。
 * 所有基礎欄位、fillable、casts 轉換以及星期幾時間運算皆自動繼承，無需重複撰寫。
 */
class Vcamp extends Camp
{
    // 1. 覆寫父類別的屬性（因為中文描述與學員營隊不同）
    public $resourceNameInMandarin = '義工營隊資料';
    
    public $resourceDescriptionInMandarin = '將義工視為一個新的營隊，可以設定義工營隊所有的基本資料(內容與學員營隊一樣)，提供營隊義工報名使用。';

    /**
     * 追加至 JSON 序列化的虛擬屬性
     * 💡 繼承自父類別的屬性會自動生效，這裡明確追加智慧自適應欄位
     */
    protected $appends = [
        'resolved_camp',
        'resolved_vcamp',
    ];

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

    /* -------------------------------------------------------------------------- */
    /* 🚀 子類別效能優化鎖定：智慧自適應修改器 (Override 父類別)                      */
    /* -------------------------------------------------------------------------- */

    /**
     * 🧠 智慧自適應：$vcamp->resolved_camp
     * 既然身為義工營隊，不需要再走父類別的 if 猜測，直接精準回傳關聯的學員主營隊（mainCamp）。
     */
    protected function resolvedCamp(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->mainCamp
        );
    }

    /**
     * 🧠 智慧自適應：$vcamp->resolved_vcamp
     * 既然我當下百分之百確定自己就是 Vcamp，直接 0 延遲在記憶體中回傳自己 ($this)！
     * 杜絕任何往上查 table 欄位字串判斷的可能，達成最高效能鎖定。
     */
    protected function resolvedVcamp(): Attribute
    {
        return Attribute::make(
            get: fn () => $this
        );
    }
}