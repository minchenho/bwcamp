<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\ApplicantsGroup;
use App\Models\Camp;
use App\Models\CampOrg;
use App\Models\CarerApplicantXref;
use App\Models\User;
use App\Services\BackendService;
use App\Services\CampManageAccessService;
use Illuminate\Http\Request;

/**
 * 後台頁面以 AJAX 呼叫的端點（routes/semi-api.php，路由群組套用 auth）
 *
 * 2026-11-14：原本完全不需登入、也沒有任何權限檢查，任何人都能指派職務（含替報名者建帳號）、
 * 改組別與關懷員、讀取整個義工營的報名資料。現在每個端點都要是該營隊的工作人員，
 * 寫入另外比照畫面上按鈕的權限（components/button/options.blade.php），並確認操作對象屬於該營隊。
 */
class SemiApiController extends Controller
{
    public function __construct(public BackendService $backendService, private CampManageAccessService $access)
    {
        parent::__construct();
    }

    /**
     * 依 camp_id 取得營隊，並確認登入者是此營隊的工作人員
     */
    private function memberCamp(Request $request, string $key = 'camp_id'): Camp
    {
        $camp = Camp::find($request->input($key));
        if (!$camp) {
            abort(404, '找不到營隊。');
        }
        if (!$this->access->isCampMember($request->user(), $camp)) {
            abort(403, '您在此營隊沒有職務。');
        }
        return $camp;
    }

    /**
     * 營隊本身、其義工營與學員營的 id（職務、梯次可能掛在任一邊）
     */
    private function relatedCampIds(Camp $camp): array
    {
        return collect([$camp->id, $camp->vcamp?->id, $camp->mainCamp?->id])->filter()->unique()->values()->all();
    }

    /**
     * 比照畫面上「設定組別／設定關懷員／設定職務」按鈕的顯示條件：該資源的 create 或 assign 權限
     */
    private function authorizeAssign(Request $request, Camp $camp, string $resourceClass): void
    {
        $user = $request->user();
        $mainCamp = $camp->isVcamp ? $camp->mainCamp : $camp;
        if ($this->access->isSuperuser($user) || $this->access->canManage($user, $mainCamp)) {
            return;
        }
        foreach (['create', 'assign'] as $action) {
            if ($mainCamp && $user->canAccessResource(new $resourceClass(), $action, $mainCamp, target: $mainCamp->vcamp)) {
                return;
            }
        }
        abort(403, '您沒有此項設定的權限。');
    }

    /**
     * 前端傳來的報名序號（可能帶 A 前綴），並確認都屬於指定的營隊
     */
    private function applicantsInCamps(Request $request, array $campIds)
    {
        $ids = collect((array) $request->input('applicant_ids'))
            ->map(fn ($id) => is_scalar($id) ? str_replace('A', '', (string) $id) : null)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique();
        if ($ids->isEmpty()) {
            abort(422, '未選擇任何人。');
        }
        $applicants = Applicant::whereIn('id', $ids)->get();
        if ($applicants->count() !== $ids->count() || $applicants->contains(fn ($a) => !in_array($a->camp_id, $campIds))) {
            abort(403, '所選的人不屬於此營隊。');
        }
        return $applicants;
    }

    public function getBatchGroups(Request $request)
    {
        $camp = $this->memberCamp($request);
        $batchGroups = $this->backendService->getBatchGroups($camp);
        if ($request->filled('batch_id')) {
            $batch = $batchGroups->first(function ($batch) use ($request) {
                return (int) $batch->id === (int) $request->input('batch_id');
            });
            $groups = $batch?->groups ?? collect();
        }
        else {
            $groups = $batchGroups->flatMap(function ($batch) {
                return $batch->groups ?? collect();
            });
        }
        $groups = $groups->map(function ($group) {
            return [
                'id' => $group->id,
                'name' => $group->batch->name . ": " . $group->alias,
            ];
        });
        return response()->json($groups->values());
    }

    public function setGroup(Request $request)
    {
        $camp = $this->memberCamp($request);
        $this->authorizeAssign($request, $camp, ApplicantsGroup::class);
        $group = ApplicantsGroup::with('batch')->find($request->input('group_id'));
        if (!$group || !in_array($group->batch?->camp_id, $this->relatedCampIds($camp))) {
            abort(403, '組別不屬於此營隊。');
        }
        $applicants = $this->applicantsInCamps($request, [$group->batch->camp_id]);
        $this->backendService->setGroupNew($applicants->pluck('id')->map(fn ($id) => (string) $id)->all(), (string) $group->id);
        return response()->json(['status' => 'success']);
    }

    public function setCarer(Request $request)
    {
        $camp = $this->memberCamp($request);
        $this->authorizeAssign($request, $camp, CarerApplicantXref::class);
        $carer = User::find($request->input('carer_id'));
        if (!$carer || !$this->access->isCampMember($carer, $camp)) {
            abort(403, '關懷員不是此營隊的工作人員。');
        }
        $applicants = $this->applicantsInCamps($request, $this->relatedCampIds($camp));
        $this->backendService->setCarer($applicants->pluck('id')->map(fn ($id) => (string) $id)->all(), (string) $carer->id);
        return response()->json(['status' => 'success']);
    }

    public function getCampOrganizations(Request $request)
    {
        $camp = $this->memberCamp($request);
        $vcamp = Camp::find($camp->vcamp?->id ?? null);
        $vbatches = $vcamp?->batches;
        $orgs = $this->backendService
                    ->getCampOrganizations($camp) ?? collect();
        $orgs = $orgs->map(function ($org) use ($vbatches) {
            if ($vbatches && $vbatches->contains($org->batch)) {
                $org->camp_name = "義工";
            }
            else {
                $org->camp_name = "學員";
            }
            return $org;
        });
        // Get region name
        $orgs = $orgs->map(function ($org) {
            $org->region_name = $org->region?->name ?? "全區";
            return $org;
        });
        // Get batch name
        $orgs = $orgs->map(function ($org) {
            $org->batch_name = $org->batch?->name ?? "不分梯";
            return $org;
        });
        $orgs = $orgs->filter(function ($org) {
                    return 1;
                })->each(function ($org) {
                    if ($org->section == "root") {
                        $org->section = "[" . $org->camp_name . "]" . $org->batch_name . $org->region_name ."大會";
                    }
                    else {
                        $org->section = str_replace("root.", " - ", $org->section);
                        $org->section = "[" . $org->camp_name . "]" . $org->batch_name . "：" . $org->region_name . $org->section;
                    }
                })->unique();
        // 依樹狀順序（同一層級依 order）；values() 讓 JSON 輸出陣列，否則前端 Object.entries 會依 id 重排
        $orgs = CampOrg::sortForDisplay($orgs);
        return response()->json($orgs);
    }

    public function getCampPositions(Request $request)
    {
        $camp = $this->memberCamp($request);
        $nodeId = $request->input('node_id');
        $target_org = CampOrg::find($nodeId);
        if (!$target_org || !in_array($target_org->camp_id, $this->relatedCampIds($camp))) {
            return response()->json([]);
        }
        $orgs = $this->backendService
                    ->getCampOrganizations($camp) ?? collect();
        $orgs = $orgs->filter(function ($org) use ($target_org) {
            if ($org->section == "root" && $target_org->section == "root") {
                return $org->prev_id == 0 && !$org->is_node && $org->region_id == $target_org->region_id;
            }
            return $org->position != 'root' && $org->section == $target_org->section && $org->prev_id == $target_org->prev_id;
        });
        $orgs = $orgs->each(function ($org) {
            if (!is_null($org->group_id)) {
                $org->batch_name = $org->batch?->name;
            }
        });
        return response()->json(CampOrg::sortForDisplay($orgs));
    }

    /**
     * 營隊管理（組織頁）用：只限可管理該營隊的人（superuser、範圍內的 event_admin）
     */
    public function getOrgSel(Request $request)
    {
        $camp = Camp::find($request->input('camp_id_sel'));
        if (!$camp || !$this->access->canManage($request->user(), $camp)) {
            abort(403, '您沒有管理此營隊的權限。');
        }
        $orgs = $camp->organizations;
        return response()->json(CampOrg::sortForDisplay($orgs));
    }

    public function getPermissionSel(Request $request)
    {
        $org = CampOrg::with('camp')->find($request->input('org_id_sel'));
        if (!$org || !$this->access->canManage($request->user(), $org->camp)) {
            abort(403, '您沒有管理此營隊的權限。');
        }
        $permissions = $org->permissions->sortBy('display_name');
        return response()->json($permissions);
    }
}
