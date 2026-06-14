<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampCustomOption extends Model
{
    /**
     * 資料表名稱 (若符合複數型規範可省略，但明確寫出更安全)
     *
     * @var string
     */
    protected $table = 'camp_custom_options';

    /**
     * 允許批量寫入的黑名單 (留空代表所有欄位皆可透過 Form 批量寫入，如 create, update)
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * 資料型態轉換 (確保金額與排序撈出來時一定是整數型態，避免前端 JS 計算出錯)
     *
     * @var array
     */
    protected $casts = [
        'camp_id' => 'integer',
        'batch_id' => 'integer',
        'amount' => 'integer',
        'sort_order' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | 🔗 關聯性設定 (Relationships)
    |--------------------------------------------------------------------------
    */

    /**
     * 隸屬於哪一個營隊
     */
    public function camp()
    {
        return $this->belongsTo(Camp::class, 'camp_id');
    }

    /**
     * 隸屬於哪一個梯次 (注意：因為通用設定會是 Null，所以使用時要防錯)
     */
    public function batch()
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    /*
    |--------------------------------------------------------------------------
    | 🎯 本地作用域查詢 (Local Scopes)
    |--------------------------------------------------------------------------
    | 寫好 Scope 後，未來在 Controller 撈資料只要寫：
    | CampCustomOption::ofCamp($campId)->ofType('industry')->get();
    | 程式碼會變得非常優雅、好讀！
    */

    /**
     * 篩選指定營隊
     */
    public function scopeOfCamp($query, $campId)
    {
        return $query->where('camp_id', $campId);
    }

    /**
     * 篩選特定類別 (例如 industry, fare_room)
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type)->orderBy('sort_order', 'asc');
    }

    /**
     * 【精華功能】自動處理「梯次特定設定 vs 全營隊通用設定」的分流邏輯
     * * 使用範例：
     * $options = CampCustomOption::ofBatchWithFallback($campId, $batchId, 'fare_room')->get();
     */
    public function scopeOfBatchWithFallback($query, $campId, $batchId, $type)
    {
        // 先嘗試找出「專屬該特定梯次」的設定
        $query->where('camp_id', $campId)
              ->where('type', $type)
              ->where('batch_id', $batchId)
              ->orderBy('sort_order', 'asc');

        // 注意：Eloquent Scope 本身必須回傳 Builder。
        // 如果要在 Scope 內做完像我們之前在 Controller 寫的「找不到就退回 NULL」的 Fallback，
        // 建議在 Repository 或 Service 層做。
        // 這個 Scope 會幫你精準定錨在特定梯次，如果要在這裡完成自動兜底，可以改寫成普通靜態 method。
    }

    /*
    |--------------------------------------------------------------------------
    | 🛠️ 靜態方法 (Static Helpers)
    |--------------------------------------------------------------------------
    | 直接封裝好最常被呼叫的萬能分流撈取邏輯！
    */

    /**
     * 萬能撈取器：專屬特定梯次 優先 ──> 找不到就用 通用營隊(batch_id IS NULL) ──> 再找不到就吐回預設
     *
     * @param int $campId 營隊ID
     * @param int|null $batchId 梯次ID
     * @param string $type 類別
     * @param array $defaultFallback 寫死在 Controller 或 Form 的預設陣列
     * @return array 排好序的選項陣列 (只拿 label)
     */
    public static function getProcessedOptions($campId, $batchId, $type, array $defaultFallback = [])
    {
        // 1. 優先撈取特定梯次
        if (!empty($batchId)) {
            $options = self::where('camp_id', $campId)
                ->where('batch_id', $batchId)
                ->where('type', $type)
                ->orderBy('sort_order', 'asc')
                ->pluck('option_label')
                ->toArray();
                
            if (!empty($options)) return $options;
        }

        // 2. 特定梯次落空，改撈「通用營隊設定 (batch_id is null)」
        $options = self::where('camp_id', $campId)
            ->whereNull('batch_id')
            ->where('type', $type)
            ->orderBy('sort_order', 'asc')
            ->pluck('option_label')
            ->toArray();

        if (!empty($options)) return $options;

        // 3. 資料庫完全沒設定，吐回預設值
        return $defaultFallback;
    }
}