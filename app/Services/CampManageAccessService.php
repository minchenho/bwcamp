<?php

namespace App\Services;

use App\Models\Camp;
use App\Models\UserCampTableScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 營隊管理（營隊 / 梯次 / 組織）的存取判斷
 *
 * 營隊管理屬於特殊權限，不透過職務權限開放：
 * - superuser：全部放行
 * - event_admin：在 user_camp_table_scopes 指定的營隊類型（含對應的義工營）內全部放行
 * - 其他人：一律不可
 *
 * 以 singleton 註冊，同一個 request 內的查詢結果會快取
 */
class CampManageAccessService
{
    /**
     * 營隊管理相關的資源，不列入職務的權限矩陣
     */
    public const RESERVED_RESOURCES = [
        \App\Models\Camp::class,
        \App\Models\Vcamp::class,
        \App\Models\Batch::class,
        \App\Models\Vbatch::class,
        \App\Models\CampOrg::class,
    ];

    /**
     * 匯出一律是整營匯出，只有範圍為「全部 / 不指定」的匯出權限才有效
     */
    public const EXPORT_RANGES = ['all', 'na'];

    private array $tablesCache = [];

    public function isSuperuser($user): bool
    {
        return $user && $user->permission === 'superuser';
    }

    public function isEventAdmin($user): bool
    {
        return $user && $user->permission === 'event_admin';
    }

    /**
     * 主營隊類型對應的義工營類型，例：ycamp => yvcamp
     */
    public static function vcampTableOf(string $table): ?string
    {
        if (str_contains($table, 'vcamp') || !str_ends_with($table, 'camp')) {
            return null;
        }
        return substr($table, 0, -4) . 'vcamp';
    }

    /**
     * event_admin 可管理的營隊類型（含義工營）；superuser 回傳 null 代表全部
     */
    public function manageableCampTables($user): ?array
    {
        if ($this->isSuperuser($user)) {
            return null;
        }
        if (!$this->isEventAdmin($user)) {
            return [];
        }
        return $this->tablesCache[$user->id] ??= UserCampTableScope::where('user_id', $user->id)
            ->pluck('camp_table')
            ->flatMap(fn ($table) => array_filter([$table, self::vcampTableOf($table)]))
            ->unique()->values()->all();
    }

    public function canManageCampTable($user, ?string $table): bool
    {
        if (!$table) {
            return false;
        }
        $tables = $this->manageableCampTables($user);
        return is_null($tables) || in_array($table, $tables, true);
    }

    /**
     * 營隊列表 / 新增營隊：superuser，或至少有一個營隊類型範圍的 event_admin
     */
    public function canCreateCamp($user): bool
    {
        $tables = $this->manageableCampTables($user);
        return is_null($tables) || count($tables) > 0;
    }

    /**
     * 可否管理此營隊（含其梯次與組織）
     */
    public function canManage($user, ?Camp $camp): bool
    {
        return $user && $camp && $this->canManageCampTable($user, $camp->table);
    }

    /**
     * 匯出名單：superuser、範圍內的 event_admin，
     * 或所屬職務有該資源（學員 / 義工）的 export 權限且範圍為全部
     *
     * @param bool $isVolunteer 匯出義工名單；傳入義工營時一律視為義工
     */
    public function canExport($user, ?Camp $camp, bool $isVolunteer = false): bool
    {
        if (!$user || !$camp) {
            return false;
        }
        if ($this->isSuperuser($user) || $this->canManageCampTable($user, $camp->table)) {
            return true;
        }

        $isVolunteer = $isVolunteer || $camp->isVcamp;
        $permissionCampId = $camp->isVcamp ? $camp->mainCamp?->id : $camp->id;
        if (!$permissionCampId) {
            return false;
        }

        $class = '\\' . ($isVolunteer ? \App\Models\Volunteer::class : \App\Models\Applicant::class);
        return DB::table('org_user')
            ->join('org_permission', 'org_permission.org_id', '=', 'org_user.org_id')
            ->join('permissions', 'permissions.id', '=', 'org_permission.permission_id')
            ->where('org_user.user_id', $user->id)
            ->where('permissions.camp_id', $permissionCampId)
            ->where('permissions.resource', $class)
            ->where('permissions.action', 'export')
            ->whereIn('permissions.range', self::EXPORT_RANGES)
            ->exists();
    }

    /**
     * 給 Blade 使用：以目前登入者判斷
     */
    public function allows(?Camp $camp): bool
    {
        return $this->canManage(auth()->user(), $camp);
    }

    /**
     * 營隊列表可看到的營隊
     */
    public function accessibleCamps($user): Collection
    {
        $tables = $this->manageableCampTables($user);
        if ($tables === []) {
            return collect();
        }
        return Camp::query()
            ->when(!is_null($tables), fn ($query) => $query->whereIn('table', $tables))
            ->orderBy('id', 'desc')
            ->get();
    }
}
