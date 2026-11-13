<?php

namespace App\Http\Middleware;

use App\Models\Batch;
use App\Models\Camp;
use App\Services\CampManageAccessService;
use Closure;

/**
 * 過期營隊（Camp::isExpired()）的前台（camp/{batch_id}/...）與報到（/checkin/*）只限 superuser
 *
 * 注意：後台的「檢視報名資料」、「修改學員（報名）資料」等按鈕開啟的是前台網址，
 * 過期營隊的這些按鈕也只有 superuser 能用；後台本身的頁面不受影響。
 *
 * 2026-11-13：約 70 個營隊的姓名與報名序號已外洩，已結束的營隊不需要再開放
 */
class CampOpen
{
    public function __construct(private CampManageAccessService $access)
    {
    }

    public function handle($request, Closure $next)
    {
        $camp = $this->resolveCamp($request);
        if (!$camp || !$camp->isExpired() || $this->access->isSuperuser(auth()->user())) {
            return $next($request);
        }

        abort(403, '此營隊已結束，相關資料不再開放查詢。如有需要，請聯絡主辦單位。');
    }

    private function resolveCamp($request): ?Camp
    {
        if ($batchId = $request->route('batch_id')) {
            return Batch::with('camp')->find($batchId)?->camp;
        }
        if ($campId = $request->route('camp_id') ?? $request->input('camp_id')) {
            return Camp::find($campId);
        }
        return null;
    }
}
