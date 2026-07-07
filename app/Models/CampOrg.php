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

class CampOrg extends LaratrustRole
{
    use LaratrustRoleTrait;

    protected $table = 'camp_org';

    public $resourceNameInMandarin = '營隊組織 / 義工職務組別';

    public $resourceDescriptionInMandarin = '在學員營隊下新增營隊職務的操作(新增/查詢/修改/刪除)。包括功能大組及各個職稱(但不須增設關懷組的各小組長/副小組長/組員)，有這個資源權限的人通常就可以直接變動營隊的職務組織架構。';

    protected $fillable = [
        'camp_id', 'batch_id', 'region_id', 'section', 'position', 'group_id', 
        'all_group', 'is_node', 'prev_id', 'order', 'display_name', 'description', 'name'
    ];

    // 當定義了 $fillable，通常不需要再定義一個空的 $guarded
    // protected $guarded = [];

    /* -------------------------------------------------------------------------- */
    /*                                標準關聯 (依外鍵邏輯修正為 belongsTo)       */
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

    // 遞迴自載入父層：用於一口氣解決 path() 的 N+1 問題
    public function ancestors(): BelongsTo
    {
        return $this->parent()->with('ancestors');
    }

    public function children(): HasMany
    {
        return $this->hasMany(CampOrg::class, 'prev_id', 'id');
    }

    /* -------------------------------------------------------------------------- */
    /*                                商業邏輯與屬性改寫                           */
    /* -------------------------------------------------------------------------- */

    // 改用 Attribute 取代原有的 user_count()，並修正記憶體暴漲問題
    protected function userCount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->users()->count() 
            // 提示：若在 Controller 使用 CampOrg::withCount('users')->get();
            // 則可以直接透過 $campOrg->users_count 取得，效能最完美
        );
    }

    public function isRoot(): bool
    {
        return empty($this->prev_id);
    }

    public function isLeaf(): bool
    {
        return !$this->is_node;
    }

    /**
     * 獲取層級路徑字串
     * 搭配 $campOrg->load('ancestors') 可以做到 0 次額外查詢
     */
    public function getPathString(): string
    {
        $path = [];
        $current = $this;

        // 利用 Eloquent 已經 eager load 的關聯往下找，避免 while 迴圈去戳資料庫
        while ($current && !$current->isRoot()) {
            if ($current->parent) {
                $path[] = $current->parent->position;
                $current = $current->parent;
            } else {
                break;
            }
        }

        return empty($path) ? 'root' : implode('', array_reverse($path));
    }

    public function next()
    {
        return $this->belongsTo(CampOrg::class, 'camp_id', 'camp_id')
                    ->where('section', '>', $this->section)
                    ->orderBy('section', 'asc');
    }
}