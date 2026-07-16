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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
    // ⚠️ 注意：若你使用下方的 loadWholeTree() 一次撈回整棵樹，
    //         記得在查詢時加上 ->without('ancestors')，避免這裡的全域預載
    //         又額外觸發一次遞迴 SQL，導致做了兩次工。
    //protected $with = ['ancestors'];
    
    protected $appends = [
        'section', // ✨ 讓這個屬性在轉 JSON 或 toArray() 時會自動被包進去
    ];
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
     * ✨ 與 isRoot() 判準統一：root 的唯一定義是 prev_id 為整數 0（且非 null）。
     */
    public function scopeRoots(Builder $query): Builder
    {
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
    /* 一次撈回整個營隊組織（記憶體建樹，適用於單一營隊資料量不大，例如 < 100 筆）      */
    /* -------------------------------------------------------------------------- */

    /**
     * 一次撈回整個營隊的組織資料，並在記憶體中完成：
     * 1. 樹狀關聯建立（parent / children / ancestors）
     * 2. 各節點的直屬人數統計（users_count）
     * 3. 各節點的累積人數統計（含所有子孫，total_users_count_cached）
     *
     * 全程只下 2 次 SQL（組織本身 1 次 + 人數統計 1 次），
     * 之後所有樹狀操作、路徑組合、人數查詢都在記憶體完成，不再觸發任何額外查詢。
     *
     * 適用情境：單一營隊組織筆數不大（例如 < 100 筆），一次全撈完全負擔得起。
     * 若營隊組織筆數可能很大，不建議使用此方法（記憶體與遞迴成本會提高）。
     *
     * @param int $campId
     * @return Collection  以 id 為 key 的組織集合，每筆都已掛好 parent/children/ancestors 關聯
     */
    public static function loadWholeTree(int $campId): Collection
    {
        // 1️⃣ 撈組織本身，取消全域 $with 的遞迴 ancestors 預載，避免多下一次遞迴 SQL
        $all = static::query()
            ->without('ancestors')
            ->where('camp_id', $campId)
            ->orderBy('order')
            ->get();

        if ($all->isEmpty()) {
            return $all;
        }

        // 2️⃣ 一次撈出所有組織的直屬人數（一次 SQL，取代逐筆 users()->count()）
        $userCounts = DB::table('org_user')
            ->select('org_id', DB::raw('COUNT(*) as cnt'))
            ->whereIn('org_id', $all->pluck('id'))
            ->groupBy('org_id')
            ->pluck('cnt', 'org_id');

        $byId = $all->keyBy('id');

        // 3️⃣ 記憶體建樹：掛上 parent / children / ancestors 關聯，並塞入直屬人數快取
        foreach ($all as $org) {
            $parent = $org->prev_id ? $byId->get($org->prev_id) : null;

            $org->setRelation('parent', $parent);
            $org->setRelation('ancestors', $parent); // ancestors() 定義上等同 parent 鏈的第一環

            $children = $all->where('prev_id', $org->id)->values();
            $org->setRelation('children', $children);

            $org->setAttribute('users_count', (int) ($userCounts[$org->id] ?? 0));
        }

        // 4️⃣ 純記憶體遞迴：由每個節點自己往下走 children 關聯加總，
        //    不依賴 depth 欄位排序（避免歷史髒資料的 depth 不準確造成誤差），
        //    只依賴 children 關聯是否正確建立。用 $memo 避免重複計算同一節點。
        $memo = [];
        $computeTotal = function (CampOrg $org) use (&$computeTotal, &$memo): int {
            if (isset($memo[$org->id])) {
                return $memo[$org->id];
            }

            $total = (int) $org->users_count;

            foreach ($org->children as $child) {
                $total += $computeTotal($child);
            }

            return $memo[$org->id] = $total;
        };

        foreach ($all as $org) {
            $org->setAttribute('total_users_count_cached', $computeTotal($org));
        }

        return $byId;
    }

    /* -------------------------------------------------------------------------- */
    /* 商業邏輯與屬性改寫                           */
    /* -------------------------------------------------------------------------- */

    /**
     * ✨ 優先讀取 loadWholeTree() 算好的記憶體快取值（users_count），
     *    沒有快取時才 fallback 查詢資料庫（適合單筆查詢情境，會有一次 SQL）。
     */
    protected function usersCount(): Attribute
    {
        return Attribute::make(
            get: fn () => isset($this->attributes['users_count'])
                ? (int) $this->attributes['users_count']
                : $this->users()->count()
            // 提示：Controller 使用 CampOrg::withCount('users')->get()
            // 或直接使用 CampOrg::loadWholeTree($campId) 效能最完美
        );
    }

    /**
     * ✨ 判準與 scopeRoots() 完全統一：root 的唯一定義是 prev_id 為整數 0（且非 null）。
     * depth === 0 僅作為輔助健檢用途，不再單獨作為判斷 root 的依據，
     * 避免 isRoot() 與 scopeRoots() 對同一筆資料的認定不一致。
     */
    public function isRoot(): bool
    {
        return $this->prev_id !== null && (int) $this->prev_id === 0;
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
     * 或直接透過 loadWholeTree() 一次撈回，parent/ancestors 皆已在記憶體中掛好。
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
        $maxLoops = $this->depth ?? 10; // 防禦防火牆
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
     * 🧠 現代化 Laravel Attribute：$org->section
     * 當呼叫 $org->section 時，自動回傳樹狀組織路徑
     */
    protected function section(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getPathString()
        );
    }

    /**
     * 🧠 遞迴獲取目前組織 + 所有子孫組織的用戶總數
     * ✨ 優先讀取 loadWholeTree() 算好的記憶體快取值（total_users_count_cached），
     *    完全不觸發任何資料庫查詢。
     *    沒有快取時才 fallback 走原本的遞迴查詢邏輯（適合單筆查詢情境）。
     */
    public function getTotalUsersCountAttribute(): int
    {
        if (isset($this->attributes['total_users_count_cached'])) {
            return (int) $this->attributes['total_users_count_cached'];
        }

        // 保底邏輯（未透過 loadWholeTree() 載入時使用，會有 N+1 風險，僅適合單筆查詢情境）
        $count = $this->users_count ?? $this->users()->count();

        $children = $this->relationLoaded('children') ? $this->children : $this->children()->get();

        foreach ($children as $child) {
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
