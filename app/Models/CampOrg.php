<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laratrust\Models\LaratrustRole;
use Laratrust\Traits\LaratrustRoleTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Builder;

class CampOrg extends LaratrustRole
{
    use LaratrustRoleTrait;

    protected $table = 'camp_orgs';

    public $resourceNameInMandarin = '營隊組織 / 義工職務組別';

    public $resourceDescriptionInMandarin = '在學員營隊下新增營隊職務的操作(新增/查詢/修改/刪除)。包括功能大組及各個職稱(但不須增設關懷組的各小組長/副小組長/組員)，有這個資源權限的人通常就可以直接變動營隊的職務組織架構。';

    /**
     * 與新版 DDL 同步：移除 section, is_node, all_group，迎來 depth
     */
    protected $fillable = [
        'camp_id', 'batch_id', 'region_id', 'position', 'depth', 'group_id', 
        'prev_id', 'order', 'display_name', 'description', 'name'
    ];

    // ✨ 核心大絕招：全域自動預載。未來全系統只要撈組織，自動把祖先鏈一網打盡！
    protected $with = ['ancestors'];

    /* -------------------------------------------------------------------------- */
    /* 標準關聯與核心多對多                        */
    /* -------------------------------------------------------------------------- */

    public function camp(): BelongsTo
    {
        return $this->belongsTo(Camp::class, 'camp_id', 'id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id', 'id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id', 'id');
    }

    public function applicant_group(): BelongsTo
    {
        return $this->belongsTo(ApplicantsGroup::class, 'group_id', 'id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'org_user', 'org_id', 'user_id');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'org_permission', 'org_id', 'permission_id');
    }

    public function dynamic_stats(): MorphMany
    {
        return $this->morphMany(DynamicStat::class, 'urltable');
    }

    /* -------------------------------------------------------------------------- */
    /*                                樹狀結構與遞迴關聯                           */
    /* -------------------------------------------------------------------------- */

    // 修正：絕不回傳 null，保持關聯完整性
    public function parent(): BelongsTo
    {
        return $this->belongsTo(CampOrg::class, 'prev_id', 'id');
    }

    /**
     * 遞迴自載入父層：用於一口氣解決 path() 的 N+1 問題
     */
    public function ancestors(): BelongsTo
    {
        return $this->parent()->with('ancestors');
    }

    public function children(): HasMany
    {
        return $this->hasMany(CampOrg::class, 'prev_id', 'id');
    }

    /* -------------------------------------------------------------------------- */
    /* 常用查詢範圍 (Query Scopes)                  */
    /* -------------------------------------------------------------------------- */

    /**
     * 限制查詢範圍為根節點 (深度安全防禦版)
     */
    public function scopeRoots(Builder $query): Builder
    {
        // ✨ 雙重防線：prev_id 必須是 0，且絕對不能是 null！
        return $query->where('prev_id', 0)
                     ->whereNotNull('prev_id');
    }
  
    /**
     * 取代原先有 Bug 的 next() 關聯方法。
     * 用法：$nextOrg = $campOrg->nextInOrder()->first();
     */
    public function scopeNextInOrder(Builder $query): Builder
    {
        return $query->where('camp_id', $this->camp_id)
                     ->where('order', '>', $this->order)
                     ->orderBy('order', 'asc');
    }

    /* -------------------------------------------------------------------------- */
    /* 商業邏輯與屬性改寫                           */
    /* -------------------------------------------------------------------------- */

    protected function userCount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->users()->count() 
            // 提示：Controller 使用 CampOrg::withCount('users')->get(); 效能最完美
        );
    }

    public function isRoot(): bool
    {
        // ✨ 嚴格判定：prev_id 必須是整數 0（或字串 '0'），null 會被排除！
        return $this->depth === 0 || ($this->prev_id !== null && (int)$this->prev_id === 0);
    }

    /**
     * 優化：移除 is_node 欄位依賴，透過關聯動態檢查記憶體或快取，確保資料不打架
     */
    public function isLeaf(): bool
    {
        if ($this->relationLoaded('children')) {
            return $this->children->isEmpty();
        }
        return !$this->children()->exists();
    }

    /**
     * 判定此職務是否管轄全營隊所有學員小組 (group_id = 0 代替原 all_group = 1)
     */
    public function isAllGroup(): bool
    {
        return $this->group_id === 0;
    }

    /**
     * 判定此職務是否為專職管轄特定小組 (如特定的第 3 組隊輔)
     */
    public function isSpecificGroup(): bool
    {
        return $this->group_id > 0;
    }

    /**
     * 獲取層級路徑字串
     * 搭配 $campOrg->load('parent.ancestors') 或 $campOrg->load('ancestors') 做到 0 次額外 SQL
     */
    public function getPathString(): string
    {
        // 【攔截】如果是斷鏈資料（不是 Root 卻沒有家長 ID）
        if (!$this->isRoot() && is_null($this->prev_id)) {
            return '【警告】組織資料斷鏈(無上層ID)';
        }

        $path = [];
        $current = $this;
        
        // 導入 depth 當作安全計數計防線，100% 避免因歷史髒資料造成的死循環
        $maxLoops = $this->depth?? 10; // 防禦防火牆
        $loopCount = 0;

        while ($current && !$current->isRoot() && $loopCount <= $maxLoops) {
            if ($current->relationLoaded('parent') && $current->parent) {
                $path[] = $current->parent->position;
                $current = $current->parent;
                $loopCount++;
            } elseif ($current->relationLoaded('ancestors') && $current->ancestors) {
                $path[] = $current->ancestors->position;
                $current = $current->ancestors;
                $loopCount++;
            } else {
                // 安全退回機制：防止無預載入時導致 N+1 爆查
                break;
            }
        }        
        return empty($path) ? 'root' : implode(' > ', array_reverse($path));
    }

    /**
     * 🧠 遞迴獲取目前組織 + 所有子孫組織的用戶總數
     */
    public function getTotalUsersCountAttribute(): int
    {
        // 1. 先拿自己這層的人數（優先用 counters 快取，沒載入就用 count()）
        $count = $this->users_count ?? $this->users()->count();

        // 2. 如果有預載 children，直接用 children 跑迴圈；否則去資料庫撈直屬下線
        $children = $this->relationLoaded('children') ? $this->children : $this->children()->get();

        foreach ($children as $child) {
            // 遞迴呼叫子節點的 total_users_count，一路往下加總
            $count += $child->total_users_count;
        }

        return $count;
    }

    /**
    * 🌲 向上追溯大組屬性：$campOrg->closest_section
    * 自動沿著樹狀結構往上爬，抓出該職務隸屬的 depth = 1 (功能大組) 節點
    */
    protected function closestSection(): Attribute
    {
        return Attribute::make(
            get: function () {
                // 👉 情況 A：自己本來就是大組級職務，那大組就是自己
                if ((int)$this->depth === 1) {
                    return $this;
                }

                // 👉 情況 B：如果是 Root (depth = 0)，一般大組長都是 depth = 1。
                // 萬一有更上層的 root，它自己不是大組，上層也沒大組，就回傳 null
                if ((int)$this->depth === 0) {
                    return null;
                }

                // 👉 情況 C：自己在更低層級 (depth > 1)，開始往上攀爬
                $current = $this;
                $safetyCounter = 0; // 髒資料死循環保險絲

                while ($current && $safetyCounter < 10) {
                    // 優先使用預載入的 ancestors，否則走 parent 關聯
                    $parent = $current->relationLoaded('ancestors') && $current->ancestors 
                        ? $current->ancestors 
                        : $current->parent;

                    if (!$parent) {
                        break; // 斷鏈安全降落
                    }

                    // 🎯 找到了！這就是我們要的 depth = 1 節點
                    if ((int)$parent->depth === 1) {
                        return $parent;
                    }

                    $current = $parent;
                    $safetyCounter++;
                }

                return null;
            }
        );
    }
}