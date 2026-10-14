<?php

namespace App\Http\Controllers;

use App\Models\Camp;
use App\Models\Batch;
use App\Models\Region;
use App\Models\CampOrg;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\BackendService;
use App\Services\CampOrgService;
use Illuminate\Support\Facades\DB;

class CampOrgController extends BackendController 
{
    protected $campOrgService;

    public function __construct(
        \App\Services\CampDataService $campDataService,
        \App\Services\ApplicantService $applicantService,
        BackendService $backendService,
        \App\Services\GSheetService $gsheetService,
        Request $request,
        \App\Services\LodgingService $lodgingService, 
        \App\Services\TrafficService $trafficService,
        CampOrgService $campOrgService
    ) {
        // 如果你的 BackendController 需要這些，繼續傳遞
        parent::__construct(
            $campDataService,
            $applicantService,
            $backendService,
            $gsheetService,
            $request,
            $lodgingService,
            $trafficService
        );
        $this->campOrgService = $campOrgService;
    }

    public function showOrgs($camp_id)
    {
        $camp = Camp::find($camp_id);
        $vcamp = $camp->resolved_vcamp;
        $vbatches = $vcamp ? $vcamp->batches : collect();
        $batches = $camp->batches->merge($vbatches);
        $regions = $camp->regions;
        
        // ✨ 新架構：依深度與自訂排序，全域預載ancestors寫在model裡面，避免N+1問題
        $orgs = $camp->orgs()
            ->with('permissions')
            ->orderBy('depth')
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        $num_users = array();
        foreach($orgs as $org) {
            $num_users[$org->id] = DB::table('org_user')->where('org_id', $org->id)->count();
        }

        $camp_list = Camp::where('table', $camp->table)->get();
        $models = $this->backendService->getAvailableModels();
        
        return view('backend.camp.orgList', compact('camp', 'batches', 'orgs', 'camp_list', 'models', 'num_users'));
    }

    public function showAddOrgs($camp_id, $org_id)
    {
        $camp = Camp::find($camp_id);
        $orgs = $camp->orgs()
            ->orderBy('depth')
            ->orderBy('order')
            ->orderBy('id')
            ->get();
        $batches = $camp->batches;
        $regions = $camp->regions;
        $org_tg = null; $batch_tg = null; $region_tg = null;
        
        if ($org_id == 0) {
            if ($orgs->isEmpty()) {
                $org_tg = CampOrg::create([
                    'camp_id' => $camp_id,
                    'position' => '大會',
                    'depth' => 0,
                    'prev_id' => 0,
                    'order' => 0
                ]);
                $orgs = $camp->orgs;
            }
            $batch_tg = new Batch(['id' => 0, 'name' => '不限']);
            $region_tg = new Region(['id' => 0, 'name' => '不限']);
        } else {
            $org_tg = CampOrg::find($org_id);
            $batch_tg = $org_tg->batch_id ? Batch::find($org_tg->batch_id) : new Batch(['id' => 0, 'name' => '不限']);
            $region_tg = $org_tg->region_id ? Region::find($org_tg->region_id) : new Region(['id' => 0, 'name' => '不限']);
        }
        return view('backend.camp.addOrgs', compact("orgs", "batches", "regions", "org_tg", "batch_tg", "region_tg"))->with('camp', $camp);
    }

    public function addOrgs(Request $request, $camp_id)
    {
        $formData = $request->toArray();
        $camp = Camp::find($camp_id);
        $orgs = $camp->orgs;
        $newSet = array();
        $is_exist = false; $existed_org = null;
        
        // 取得陣列的第一個元素，並轉為整數 (int)
        $prevId = (int) ($formData['prev_id'][0] ?? 0);
        // 使用 firstWhere 或整數比對
        $org_parent = $orgs->firstWhere('id', $prevId);
        
        $positions = count($formData['position']);
        
        foreach($formData as $key => $field) {
            if ($key == 'position') {
                $j = 0;
                for($i = 0; $i < $positions; $i++) {
                    while(!isset($field[$j])) { $j++; }
                    $pos_tg = $field[$j];
                    $newSet[$j]['camp_id'] = $camp_id;
                    $newSet[$j]['batch_id'] = $formData['batch_id'][$j] ?: null;
                    $newSet[$j]['region_id'] = $formData['region_id'][$j] ?: null;
                    $newSet[$j]['position'] = $pos_tg;
                    $newSet[$j]['depth'] = $org_parent ? $org_parent->depth + 1 : 0;
                    $newSet[$j]['prev_id'] = $formData['prev_id'][$j] ?: 0;
                    $newSet[$j]['order'] = $formData['order'][$j] ?? 0;
                    $newSet[$j]['group_id'] = (($formData['all_group'][$j] ?? 0) == 1) ? 0 : ($formData['group_id'][$j] ?? null);

                    foreach($orgs as $org) {
                        if ($org->position == $pos_tg && $org->prev_id == $newSet[$j]['prev_id'] && $org->batch_id == $newSet[$j]['batch_id'] && $org->region_id == $newSet[$j]['region_id']) {
                            $is_exist = true; $existed_org = $org; break;
                        }
                    }
                    if (!$is_exist) {
                        CampOrg::create($newSet[$j]);
                    }
                    $j++;
                }
            }
        }
        
        // 只要有一筆新增，就重新刷整顆樹的 depth
        $this->campOrgService->rebuildTree($camp->orgs);
        
        if (!$is_exist) {
            \Session::flash('message', "組織職務新增成功。");
            return redirect()->route("showOrgs", $camp_id);
        } else {
            return redirect()->route("showOrgs", $camp_id)->withErrors(['職務已存在，ID：' . $existed_org->id]);
        }
    }

    public function showModifyOrg($camp_id, $org_id)
    {
        $camp = Camp::with(['batches', 'vcamp', 'vcamp.batches' ])->find($camp_id);
        $org = CampOrg::find($org_id);
        $availableResources = BackendService::getAvailableModels();
        view()->share('availableResources', $availableResources);
        $isCopyPermissions = false;
        $camp_list = [];
        $models = [];
        return view('backend.camp.modifyOrg', compact("camp", "org", "isCopyPermissions", "camp_list", "models"))->with('complete_permissions', $org->permissions);
    }

    public function modifyOrg(Request $request, $camp_id, $org_id)
    {
        $camp = Camp::find($camp_id);
        $org_tg = CampOrg::find($org_id);

        // 1. 處理 Laratrust 權限矩陣
        $totalPermissions = $this->backendService->permissionTableProcessor($request, $org_tg->id, $camp);
        if (!is_array($totalPermissions)) { return $totalPermissions; }

        // 2. 核心：精準清洗 group_id 規格
        $allGroupMode = $request->input('all_group');
        if ($allGroupMode === 'all') {
            $org_tg->group_id = 0;
        } elseif ($allGroupMode === 'none') {
            $org_tg->group_id = null;
        } else {
            $org_tg->group_id = $request->input('group_id') ?: null; 
        }

        // 3. 唯獨更新其他安全的表單欄位
        $org_tg->batch_id = $request->input('batch_id') ?: null;
        $org_tg->region_id = $request->input('region_id') ?: null;
        
        // 只有「非根節點」才可以改名稱、排序以及「移動組織(prev_id)」
        if (!$org_tg->isRoot()) {
            $org_tg->position = $request->input('position');
            $org_tg->order = $request->input('order', 0);

            // ✨ 【新功能】移動組織邏輯
            $newPrevId = (int) $request->input('prev_id');
            
            // 安全防火牆：只有當主管真的改了上層，且新上層不是自己時才觸發
            if ($newPrevId !== $org_tg->prev_id && $newPrevId !== $org_tg->id) {
                // 🛑 進階防禦：防止主管把部門改掛到「自己的子孫」下面，這會導致斷鏈死循環！
                // 這裡檢查新上層的 ancestors 裡面有沒有包含目前要移動的自己
                $newParent = CampOrg::with('ancestors')->find($newPrevId);
                
                $isDescendant = false;
                $currentCheck = $newParent;
                while ($currentCheck) {
                    if ($currentCheck->id == $org_tg->id) {
                        $isDescendant = true;
                        break;
                    }
                    $currentCheck = $currentCheck->ancestors;
                }

                if ($isDescendant) {
                    return redirect()->back()->withErrors(['prev_id' => '不能將組織改掛到自己的子代或孫代組織下方！']);
                }

                // 通過安全檢查，正式換爸爸！
                $org_tg->prev_id = $newPrevId;
            }
        }
        // ✨ 在 save() 之前，先偷偷記錄：主管這一次到底有沒有「搬家（改上層）」？
        $isStructureChanged = $org_tg->isDirty('prev_id');

        $org_tg->save(); // 儲存組織變更

        // 4. 同步權限關係
        $org_tg->syncPermissions($totalPermissions);
        
        // 5. 如果有改prev_id，重新計算、洗刷整棵樹的關係
            // 加上 ()->get()，強迫 Laravel 重新去資料庫拉出最新組織關聯！
        if ($isStructureChanged) {
            $this->campOrgService->rebuildSubTree($org_tg, $org_tg->prev_id);
        }

        \Session::flash('message', $camp->abbreviation . " 組織職務：" . $org_tg->position . " 修改成功。");
        return redirect()->route("showOrgs", $camp_id);
    }

    public function duplicateOrg($camp_id, $org_id)
    {
        $orgSrc = CampOrg::find($org_id);
        $orgDst = $orgSrc->replicate();
        $orgDst->position = $orgSrc->position . "copy";
        $orgDst->created_at = Carbon::now();
        $orgDst->save();
        
        $this->campOrgService->rebuildTree(CampOrg::where('camp_id', $camp_id)->get());
        $this->campOrgService->duplicatePermissions($orgDst, $orgSrc);
        
        \Session::flash('message', $orgSrc->position ." 複製成功。");
        return redirect()->route("showOrgs", $camp_id);
    }

    public function copyOrgs(Request $request, $camp_id)
    {
        $formData = $request->toArray();
        $campDst = Camp::find($camp_id);
        $campSrc = Camp::find($formData['camp2copy']);

        if ($campDst->batches->count() != $campSrc->batches->count()) {
            \Session::flash('error', "梯次數量不同，無法複製"); return back();
        }
        
        $batchIdMatchList = array("0" => 0);
        foreach($campSrc->batches as $batchSrc) {
            $batchDst = $campDst->batches->where('name', $batchSrc->name)->first();
            $batchIdMatchList[$batchSrc->id] = $batchDst?->id ?? null;
            if ($batchSrc->vbatch) {
                $batchIdMatchList[$batchSrc->vbatch->id] = $batchDst?->vbatch?->id ?? null;
            }
        }

        DB::transaction(function () use ($campSrc, $camp_id, $batchIdMatchList, $campDst, $formData) {
            // 來源 org id => 目的 org id，用來把 prev_id 換成新營隊的 id
            $orgIdMatchList = [];
            $orgsDst = [];

            // 目的營隊若已有根節點（例如進入新增職務頁時自動建立的「大會」），沿用它，避免出現兩個根
            $rootDst = CampOrg::where('camp_id', $camp_id)->roots()->first();

            foreach ($campSrc->organizations as $org) {
                if ($rootDst && $org->isRoot()) {
                    $orgIdMatchList[$org->id] = $rootDst->id;
                    continue;
                }
                $orgDst = $org->replicate();
                $orgDst->camp_id = $camp_id;
                if ($orgDst->batch_id) {
                    $orgDst->batch_id = $batchIdMatchList[$org->batch_id] ?? null;
                }
                $orgDst->created_at = Carbon::now();
                $orgDst->save();
                $orgIdMatchList[$org->id] = $orgDst->id;
                $orgsDst[$org->id] = $orgDst;

                if($formData['do_copy_permissions'] ?? false) {
                    $this->campOrgService->copyPermissions($campDst, $campSrc, $orgDst, $org, $batchIdMatchList);
                }
            }

            // 全部建立完才能對應 id（來源的順序不保證父節點先於子節點）
            foreach ($orgsDst as $orgDst) {
                if ((int) $orgDst->prev_id !== 0) {
                    $orgDst->prev_id = $orgIdMatchList[$orgDst->prev_id] ?? null;
                    $orgDst->save();
                }
            }
            $this->campOrgService->rebuildTree(CampOrg::where('camp_id', $camp_id)->get());
        });

        \Session::flash('message', "組織架構與職務複製成功。");
        return redirect()->route("showOrgs", $camp_id);
    }

    public function removeOrg(Request $request)
    {
        $org_tg = CampOrg::find($request->org_id);
        if (!$org_tg) { \Session::flash('error', "找不到該職務。"); return back(); }
        
        $camp_id = $org_tg->camp_id;
        $org_tg->delete();

        $this->campOrgService->rebuildTree(CampOrg::where('camp_id', $camp_id)->get());
        
        \Session::flash('message', "職務刪除成功。");
        return back();
    }

    /**
     * 直接指定使用者職務：僅限 superuser
     */
    private function ensureCanAssignOrgs(): void
    {
        if (!auth()->user()?->canAssignOrgsDirectly()) {
            abort(403, '您沒有直接指定職務的權限。');
        }
    }

    public function showAssignUsers(Request $request, $camp_id)
    {
        $this->ensureCanAssignOrgs();

        $camp = Camp::findOrFail($camp_id);
        $orgs = $camp->orgs()
            ->with('batch')
            ->orderBy('depth')
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        $assignments = DB::table('org_user')
            ->join('users', 'users.id', '=', 'org_user.user_id')
            ->whereIn('org_user.org_id', $orgs->pluck('id'))
            ->select('org_user.org_id', 'users.id as user_id', 'users.name', 'users.email')
            ->orderBy('users.name')
            ->get();

        $keyword = trim((string) $request->input('q'));
        $searchResults = collect();
        if ($keyword !== '') {
            $searchResults = \App\Models\User::query()
                ->where(function ($query) use ($keyword) {
                    $query->where('name', 'like', "%{$keyword}%")
                          ->orWhere('email', 'like', "%{$keyword}%");
                    if (ctype_digit($keyword)) {
                        $query->orWhere('id', (int) $keyword);
                    }
                })
                ->orderBy('name')
                ->limit(30)
                ->get(['id', 'name', 'email']);
        }

        return view('backend.camp.assignOrgUsers', compact('camp', 'orgs', 'assignments', 'keyword', 'searchResults'));
    }

    public function assignUser(Request $request, $camp_id)
    {
        $this->ensureCanAssignOrgs();

        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'org_ids' => 'required|array|min:1',
            'org_ids.*' => 'integer',
        ]);

        // 只接受屬於本營隊的職務
        $orgs = CampOrg::where('camp_id', $camp_id)->whereIn('id', $request->input('org_ids'))->get();
        if ($orgs->count() != count(array_unique($request->input('org_ids')))) {
            \Session::flash('error', "選擇的職務不屬於本營隊。");
            return back();
        }

        $user = \App\Models\User::findOrFail($request->input('user_id'));
        foreach ($orgs as $org) {
            \App\Models\OrgUser::firstOrCreate([
                'org_id' => $org->id,
                'user_id' => $user->id,
                'user_type' => 'App\Models\User',
            ]);
        }

        \Session::flash('message', "已將 {$user->name} 指定為：" . $orgs->pluck('position')->implode('、'));
        return back();
    }

    public function unassignUser(Request $request, $camp_id)
    {
        $this->ensureCanAssignOrgs();

        $request->validate([
            'user_id' => 'required|integer',
            'org_id' => 'required|integer',
        ]);

        $org = CampOrg::where('camp_id', $camp_id)->find($request->input('org_id'));
        if (!$org) { \Session::flash('error', "找不到該職務。"); return back(); }

        $deleted = \App\Models\OrgUser::where('org_id', $org->id)
            ->where('user_id', $request->input('user_id'))
            ->delete();

        \Session::flash($deleted ? 'message' : 'error', $deleted ? "已移除職務「{$org->position}」。" : "該使用者沒有這個職務。");
        return back();
    }

    public function saveAsPermissionTemplate(Request $request, $camp_id, $org_id)
    {
        $org = CampOrg::where('camp_id', $camp_id)->find($org_id);
        if (!$org) { \Session::flash('error', "找不到該職務。"); return back(); }

        $request->validate([
            'template_name' => 'required|string|max:255',
            'template_description' => 'nullable|string|max:255',
        ]);

        if ($org->permissions->isEmpty()) {
            \Session::flash('error', "此職務尚未設定任何權限，無法另存為範本。");
            return back();
        }

        $campTable = $request->boolean('limit_camp_table') ? $org->camp->table : null;
        $template = $this->campOrgService->saveAsTemplate(
            $org,
            $request->input('template_name'),
            $request->input('template_description'),
            $campTable,
            auth()->id()
        );

        \Session::flash('message', "已將「{$org->position}」的權限另存為範本「{$template->name}」，共 {$template->items()->count()} 項。");
        return back();
    }

    public function showCopyPermissions($camp_id, $org_id)
    {
        $camp = Camp::with(['batches', 'vcamp', 'vcamp.batches' ])->find($camp_id);
        $org = CampOrg::find($org_id);
        $availableResources = BackendService::getAvailableModels();
        view()->share('availableResources', $availableResources);
        $isCopyPermissions = true;
        $camp_list = Camp::where('table', $camp->table)->get();
        $models = $this->backendService->getAvailableModels();
        return view('backend.camp.modifyOrg', compact("camp", "org", "isCopyPermissions", "camp_list", "models"))->with('complete_permissions', $org->permissions);
    }

    public function copyPermissions(Request $request, $camp_id, $org_id)
    {
        $orgIdSrc = $request->input('org2copy');
        $orgDst = CampOrg::find($org_id);
        $orgSrc = CampOrg::find($orgIdSrc);
        
        if (!$orgDst || !$orgSrc) {
            \Session::flash('error', "找不到來源或目標職務。");
            return back();
        }
        $campDst = $orgDst->camp;
        $campSrc = $orgSrc->camp;

        $batchIdMatchList = array("0" => 0);
        foreach($campSrc->batches as $batchSrc) {
            $batchDst = $campDst->batches->where('name', $batchSrc->name)->first();
            $batchIdMatchList[$batchSrc->id] = $batchDst?->id ?? null;
            if ($batchSrc->vbatch) {
                $batchIdMatchList[$batchSrc->vbatch->id] = $batchDst?->vbatch?->id ?? null;
            }
        }

        $this->campOrgService->copyPermissions($campDst, $campSrc, $orgDst, $orgSrc, $batchIdMatchList);

        \Session::flash('message', "職務權限複製成功。");
        return redirect()->route('showModifyOrg', [$camp_id, $org_id]);
    }
}