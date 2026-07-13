<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Class Vbatch
 * * 義工梯次模型
 * 本模型採用單一表繼承 (Single Table Inheritance) 架構，直接繼承自 Batch。
 * 所有基礎欄位、fillable、casts 轉換以及星期幾時間運算（如 batch_start_weekday）皆自動繼承，無需重複撰寫。
 */
class Vbatch extends Batch
{
    // 與學員梯次共用同一張資料表
    protected $table = 'batches';

    public $resourceNameInMandarin = '義工梯次資料';

    public $resourceDescriptionInMandarin = '在義工營隊裡的皆是義工梯次，它和主營隊的梯次理論上是一對一。';

    /**
     * 追加至 JSON 序列化的虛擬屬性
     * 💡 繼承自父類別的星期幾屬性會自動生效，這裡明確追加智慧自適應欄位
     */
    protected $appends = [
        'resolved_batch',
        'resolved_vbatch',
    ];

    /**
     * 🛡️ 全域作用域安全防護 (Global Scope)
     * 確保使用 Vbatch::all() 或 Vbatch::find() 時，只會撈出屬於義工營隊（Vcamp）的梯次。
     * 避免污染到一般學員的梯次資料。
     */
    protected static function booted()
    {
        static::addGlobalScope('is_vbatch', function (Builder $builder) {
            $builder->whereHas('camp', function ($query) {
                $query->where('table', 'like', '%vcamp%');
            });
        });
    }

    /* -------------------------------------------------------------------------- */
    /* 核心標準關聯                                                                */
    /* -------------------------------------------------------------------------- */

    /**
     * 🔄 反向關聯：由義工梯次 (Vbatch) 反查它的學員主梯次 (Batch)
     */
    public function mainBatch(): HasOneThrough
    {
        return $this->hasOneThrough(
            Batch::class, 
            BatchVbatchXref::class, 
            'vbatch_id', 
            'id', 
            'id', 
            'batch_id'
        );
    }

    /**
     * 🔄 正向關聯：所屬的義工營隊 (Vcamp)
     * 基於單一表繼承，Vcamp 本質也是 camps 表，指向 Camp::class 最為穩固安全
     */
    public function vcamp(): BelongsTo
    {
        return $this->belongsTo(Camp::class, 'camp_id');
    }

    /**
     * 💡 別名關聯：為了維持與 Batch 完全相同的開發體感，讓呼叫 $vbatch->camp 也能通
     */
    public function camp(): BelongsTo
    {
        return $this->vcamp();
    }

    /* -------------------------------------------------------------------------- */
    /* 🚀 子類別效能優化鎖定：智慧自適應修改器 (Override 父類別)                      */
    /* -------------------------------------------------------------------------- */

    /**
     * 🧠 智慧自適應：$vbatch->resolved_batch
     * 既然身為義工梯次，不走父類別的 if 猜測判斷，直接精準回傳關聯的學員主梯次。
     */
    protected function resolvedBatch(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->mainBatch
        );
    }

    /**
     * 🧠 智慧自適應：$vbatch->resolved_vbatch
     * 既然我當下百分之百確定自己就是 Vbatch，直接 0 延遲回傳自己 ($this)！
     * 杜絕任何往上查 camp 關聯而可能觸發的 SQL N+1 查詢，達成最高效能鎖定。
     */
    protected function resolvedVbatch(): Attribute
    {
        return Attribute::make(
            get: fn () => $this
        );
    }
}