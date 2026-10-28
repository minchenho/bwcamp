<?php

namespace App\Services;

use App\Models\Camp;

/**
 * 營隊後台「名單 › 學員／義工資料 › 關懷記錄」一路的麵包屑
 *
 * 從全營關懷記錄進入時（from=campLogs），改走「全營關懷記錄 › 某某的關懷記錄」
 */
class InCampBreadcrumb
{
    public const FROM_CAMP_LOGS = 'campLogs';

    /**
     * @param Camp        $camp      營隊（名單頁：學員營或義工營；有學員時以學員所屬營隊為準）
     * @param object|null $applicant 學員或義工；null 代表停在名單或全營關懷記錄
     * @param string      $level     list | info | logs | add | modify | campLogs
     * @param string|null $from      來源，目前只有 campLogs
     * @return array<int, array{0: string, 1: ?string}> [文字, 網址]；最後一層網址為 null
     */
    public static function items(Camp $camp, $applicant, string $level, ?string $from = null): array
    {
        // 重新查一次：營隊後台的 camp_info 可能混入梯次欄位；有學員時以學員所屬營隊為準
        $camp = Camp::find($applicant?->camp_id ?? $camp->id) ?? $camp;
        $isVolunteer = $camp->isVcamp;
        $mainCamp = $isVolunteer ? $camp->mainCamp : $camp;
        $items = [[$mainCamp->abbreviation, route('campIndex', $mainCamp->id)]];

        if ($level === 'campLogs') {
            $items[] = ['全營關懷記錄', null];
            return $items;
        }

        $applicantId = $applicant?->applicant_id ?? $applicant?->id;
        $logsUrl = $applicant ? route('showContactLogs', [$camp->id, $applicantId]) : null;
        if ($from === self::FROM_CAMP_LOGS && !$isVolunteer) {
            $items[] = ['全營關懷記錄', route('showCampContactLogs', $mainCamp->id)];
            $items[] = [$applicant->name . ' 的關懷記錄', $logsUrl . '?from=' . self::FROM_CAMP_LOGS];
        } else {
            $listRoute = $isVolunteer ? 'showVolunteers' : 'showLearners';
            $items[] = [$isVolunteer ? '義工名單' : '學員名單', route($listRoute, $mainCamp->id) . '?restore=1'];
            if ($applicant) {
                $items[] = [$applicant->name, route('showAttendeeInfoGET', $camp->id) . '?snORadmittedSN=' . $applicantId];
                if ($level !== 'info') {
                    $items[] = ['關懷記錄', $logsUrl];
                }
            }
        }
        if ($level === 'add') {
            $items[] = ['新增關懷記錄', null];
        } elseif ($level === 'modify') {
            $items[] = ['修改關懷記錄', null];
        }

        // 最後一層是目前頁面，不加連結
        $items[array_key_last($items)][1] = null;
        return $items;
    }
}
