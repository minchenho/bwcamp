<?php

namespace App\Http\Middleware;

use App\Models\Camp;
use App\Services\CampManageAccessService;
use Closure;

/**
 * 營隊後台（backend/{camp_id}/...）與報到（/checkin/*）的進入門檻：只有此營隊的工作人員可以進入
 * （規則見 CampManageAccessService::isCampMember()），各頁面再依職務權限做細部檢查
 *
 * 2026-11-13：曾有人以前台自行註冊的帳號直接存取後台網址，
 * 部分頁面沒有權限檢查（例：銷帳資料），因此在路由層先擋下沒有職務的帳號。
 */
class CampMember
{
    public function __construct(private CampManageAccessService $access)
    {
    }

    public function handle($request, Closure $next)
    {
        $user = auth()->user();
        if (!$user) {
            return redirect()->guest(route('login'));
        }
        if ($this->access->isSuperuser($user)) {
            return $next($request);
        }

        // 營隊後台的 camp_id 在路由參數；報到（/checkin/*）的 camp_id 在網址查詢字串或表單欄位
        $campId = $request->route('camp_id') ?? $request->input('camp_id');
        if (!$campId) {
            abort(400, '請先選擇營隊。');
        }
        $camp = Camp::find($campId);
        if (!$camp) {
            abort(404);
        }
        if (!$this->access->isCampMember($user, $camp)) {
            abort(403, '您在此營隊沒有職務，無法進入營隊後台。');
        }
        return $next($request);
    }
}
