<?php

namespace App\Http\Middleware;

use App\Models\Batch;
use App\Models\Camp;
use App\Models\CampOrg;
use App\Services\CampManageAccessService;
use Closure;
use Illuminate\Support\Facades\View;

class Admin
{
    /**
     * 只限 superuser 的路由
     */
    private const SUPERUSER_ONLY = [
        // 使用者與（舊版）角色管理
        'switchToUser', 'userlist', 'userAddRole', 'removeRole', 'addRole',
        'editRoleGET', 'editRole', 'rolelist', 'listRemoveRole', 'listAddRoleGET', 'listAddRole',
        // 直接指定使用者職務
        'showAssignUsers', 'assignUser', 'unassignUser',
        // event_admin 範圍管理
        'showEventAdmins', 'saveEventAdmin', 'removeEventAdmin',
    ];

    /**
     * superuser，或有營隊類型範圍的 event_admin
     */
    private const EVENT_ADMINS = ['campManagement', 'showAddCamp', 'addCamp'];

    /**
     * 針對單一營隊的管理：superuser，或該營隊類型在範圍內的 event_admin
     */
    private const CAMP_MANAGERS = [
        'showCampToolbox', 'showModifyCamp', 'modifyCamp',
        'showBatch', 'showModifyBatch', 'showAddBatch', 'addBatch', 'copyBatch', 'modifyBatches', 'removeBatch',
        'showOrgs', 'showModifyOrg', 'showAddOrgs', 'addOrgs', 'copyOrgs', 'duplicateOrg', 'modifyOrg',
        'saveAsPermissionTemplate', 'removeOrg', 'showCopyPermissions', 'copyPermissions',
        'showGroups', 'generateGroups', 'updateGroups', 'removeGroup', 'generateCarerOrgs',
    ];

    public function __construct(private CampManageAccessService $access)
    {
    }

    public function handle($request, Closure $next)
    {
        $user = auth()->user();
        if (!$user) {
            return redirect()->guest(route('login'));
        }
        View::share('campAccess', $this->access);

        $routeName = $request->route()?->getName();

        // 從「切換使用者」切回原本身分
        if ($routeName === 'switchUserBack') {
            return $request->session()->has('original_user') ? $next($request) : abort(403);
        }
        if ($this->access->isSuperuser($user)) {
            return $next($request);
        }
        if (in_array($routeName, self::EVENT_ADMINS, true)) {
            return $this->access->canCreateCamp($user) ? $next($request) : abort(403, '您沒有營隊管理的權限。');
        }
        if (in_array($routeName, self::CAMP_MANAGERS, true)) {
            $camp = $this->resolveCamp($request);
            if (!$camp) {
                abort(404);
            }
            return $this->access->canManage($user, $camp) ? $next($request) : abort(403, '您沒有此營隊的管理權限。');
        }

        // 未列入規則的路由一律只限 superuser
        abort(403);
    }

    /**
     * 找出要檢查權限的營隊：優先依操作對象（梯次 / 職務）所屬營隊，
     * 並確認與網址中的 camp_id 一致，避免用 A 營隊的權限改到 B 營隊的資料
     */
    private function resolveCamp($request): ?Camp
    {
        $routeCampId = $request->route('camp_id');
        $campIds = [];

        $batchId = $request->route('batch_id') ?? $request->input('batch_id');
        if ($batchId) {
            $batch = Batch::find($batchId);
            if (!$batch) {
                return null;
            }
            $campIds[] = $batch->camp_id;
        }

        $orgId = $request->route('org_id') ?? $request->input('org_id');
        if ($orgId) {
            $org = CampOrg::find($orgId);
            if (!$org) {
                return null;
            }
            $campIds[] = $org->camp_id;
        }

        if ($routeCampId) {
            $campIds[] = (int) $routeCampId;
        }

        $campIds = array_unique(array_map('intval', $campIds));
        if (count($campIds) !== 1) {
            return null;
        }
        return Camp::find($campIds[0]);
    }
}
