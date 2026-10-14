<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\Batch;
use App\Models\BatchVbatchXref;
use App\Models\Camp;
use App\Models\CampVcampXref;
use App\Models\CampOrg;
use App\Models\DynamicStat;
use App\Models\Permission;
use App\Models\Region;
use App\Models\Role;
use App\Models\Vcamp;
use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use App\Services\ApplicantService;
use App\Services\BackendService;
use App\Services\CampDataService;
use App\Services\CampManageAccessService;
use App\Services\GSheetService;
//use App\Services\CampOrgService;
use App\Services\LodgingService;
use App\Services\TrafficService;

class AdminController extends BackendController {

    //protected $campOrgService;

    public function __construct(
        CampDataService $campDataService,
        ApplicantService $applicantService,
        BackendService $backendService,
        GSheetService $gsheetService,
        Request $request,
        LodgingService $lodgingService, 
        TrafficService $trafficService,
        //CampOrgService $campOrgService
    ) {
        parent::__construct(
            $campDataService,
            $applicantService,
            $backendService,
            $gsheetService,
            $request,
            $lodgingService,
            $trafficService
        );
        // $this->campOrgService = $campOrgService;
    }

    public function userlist(){
        return view('backend.user.list', ['users' => \App\User::all()]);
    }

    public function userAddRole($user_id){
        $user = \App\User::find($user_id);
        return view('backend.user.userAddRole',
        ['user' => $user,
        'roles_available' => \App\Models\Role::whereNotIn('id', $user->role_relations->pluck('role_id'))->get()]);
    }

    public function removeRole(Request $request){
        $result = \App\Models\RoleUser::where('user_id', $request->user_id)->where('role_id', $request->role_id)->delete();
        if($result){
            \Session::flash('message', "權限刪除成功。");
            return back();
        }
        else{
            \Session::flash('error', "權限刪除失敗。");
            return back();
        }
    }

    public function addRole(Request $request){
        $result = new \App\Models\RoleUser;
        $result->user_id = $request->user_id;
        $result->role_id = $request->role_id;
        $result->save();
        if($result){
            \Session::flash('message', "權限新增成功。");
            return back();
        }
        else{
            \Session::flash('error', "權限新增失敗。");
            return back();
        }
    }

    public function rolelist(){
        return view('backend.user.rolelist', ['roles' => \App\Models\Role::all()]);
    }

    public function listRemoveRole(Request $request){
        $result = \App\Models\Role::find($request->role_id)->delete();
        if($result){
            \Session::flash('message', "角色刪除成功。");
            return back();
        }
        else{
            \Session::flash('error', "角色刪除失敗。");
            return back();
        }
    }

    public function listAddRole(Request $request){
        if ($request->isMethod('GET')) {
            return view('backend.user.roleForm', ['camps' => Camp::all()]);
        }
        if ($request->isMethod('POST')) {
            $result = new \App\Models\Role;
            $result->name = $request->name;
            $result->level = $request->level;
            $result->camp_id = $request->camp_id;
            $result->region = $request->region;
            $result->save();
            if($result){
                \Session::flash('message', "角色新增成功。");
                return redirect()->route('rolelist');
            }
            else{
                \Session::flash('error', "角色新增失敗。");
                return redirect()->route('rolelist');
            }
        }
    }

    public function editRole($role_id, Request $request){
        if ($request->isMethod('GET')) {
            $role = Role::find($role_id);
            return view('backend.user.roleForm', ['camps' => Camp::all(), 'role' => $role]);
        }
        if ($request->isMethod('POST')) {
            $role = Role::find($role_id);
            $role->name = $request->name;
            $role->level = $request->level;
            $role->camp_id = $request->camp_id;
            $role->region = $request->region;
            $role->save();
            if($role){
                \Session::flash('message', "角色修改成功。");
                return redirect()->route('rolelist');
            }
            else{
                \Session::flash('error', "角色修改失敗。");
                return redirect()->route('rolelist');
            }
        }
    }

    public function showJobs(){
        $jobs = \DB::table('jobs')->get();
        $failedJobs = \DB::table('failed_jobs')->get();
        $jobs = json_decode($jobs, true);
        $failedJobs = json_decode($failedJobs, true);
        return view('backend.jobs', compact('jobs', 'failedJobs'));
    }

    public function failedJobsClear(){
        return \DB::table('failed_jobs')->truncate();
    }

    public function campManagement(CampManageAccessService $access){
        // superuser：全部；event_admin：範圍內的類型；其他人：有營隊 / 梯次 / 組織權限的營隊
        $camps = $access->accessibleCamps(auth()->user());
        $canCreateCamp = $access->canCreateCamp(auth()->user());
        return view('backend.camp.list', compact('camps', 'canCreateCamp'));
    }

    public function addCamp(Request $request, CampManageAccessService $access){
        if (!$access->canManageCampTable(auth()->user(), $request->input('table'))) {
            \Session::flash('error', "您不能建立此類型的營隊。");
            return back()->withInput();
        }
        $formData = $request->toArray();
        $camp = Camp::create($formData);
        $campName = $formData["abbreviation"];
        foreach($request->regions ?? [] as $region){
            $camp->regions()->attach($region);
        }
        \Session::flash('message', $campName . " 新增成功。");
        return redirect()->route("campManagement");
    }

    public function showAddCamp(CampManageAccessService $access){
        return view('backend.camp.campForm', ["action" => "建立", "actionURL" => route("addCamp"), "allowedTables" => $access->manageableCampTables(auth()->user())]);
    }

    public function modifyCamp(Request $request, $camp_id, CampManageAccessService $access){
        $formData = $request->toArray();
        $camp = Camp::find($camp_id);
        // 營隊類型：superuser 可任意更改；event_admin 只能改成範圍內的類型；其他人不能更改
        if ($request->has('table') && $request->input('table') !== $camp->table
            && !$access->canManageCampTable(auth()->user(), $request->input('table'))) {
            \Session::flash('error', "您不能將營隊改成此類型。");
            return back()->withInput();
        }
        if ($request->vcamp_id && !$access->can(auth()->user(), Camp::find($request->vcamp_id), 'camp', 'update')) {
            \Session::flash('error', "您沒有連結此義工營隊的權限。");
            return back()->withInput();
        }
        $camp->update($formData);
        $campName = $formData["abbreviation"];
        $camp->regions()->detach();
        foreach($request->regions ?? [] as $region){
            $camp->regions()->attach($region);
        }
        if ($request->vcamp_id) {
            CampVcampXref::updateOrCreate(["camp_id" => $camp_id], ["vcamp_id" => $request->vcamp_id]);
        }
        \Session::flash('message', $campName . " 修改成功。");
        return redirect()->route("campManagement");
    }

    public function showModifyCamp($camp_id, CampManageAccessService $access){
        $camp = Camp::find($camp_id);
        $camp_orgs = $camp->orgs;
        $vcamps = Camp::where('registration_end', '>', now()->year . "-01-01")->where('table', 'like', '%vcamp%')->get()
            ->filter(fn ($vcamp) => $access->can(auth()->user(), $vcamp, 'camp', 'update'))->values();
        // 一般使用者不能更改營隊類型，只保留目前的類型
        $allowedTables = $access->manageableCampTables(auth()->user());
        if (is_array($allowedTables) && !in_array($camp->table, $allowedTables, true)) {
            $allowedTables = [$camp->table];
        }
        return view('backend.camp.campForm', ["action" => "修改", "actionURL" => route("modifyCamp", $camp->id), "camp" => $camp, "vcamps" => $vcamps, "allowedTables" => $allowedTables]);
    }

    public function addBatches(Request $request, $camp_id){
        $formData = $request->toArray();
        $newSet = array();
        $batches = count($formData['name']);
        for($i = 0; $i < $batches; $i++){
            foreach($formData as $key => $field){
                if($key == 'is_late_registration_end' && $field[$i] == ''){
                    continue;
                }
                $newSet[$i][$key] = $field[$i];
            }
            $newSet[$i]['camp_id'] = $camp_id;
            Batch::create($newSet[$i]);
        }
        \Session::flash('message', " 梯次新增成功。");
        return redirect()->route("showBatch", $camp_id);
    }

    public function copyBatch(Request $request, $camp_id){
        $formData = $request->toArray();
        //$newSet = array();
        $batch = Batch::find($formData['batch_id']);
        $newBatch = $batch->replicate();
        $newBatch->created_at = Carbon::now();
        $newBatch->save();
        \Session::flash('message', " 梯次複製成功。");
        return redirect()->route("showBatch", $camp_id);
    }

    public function showAddBatch($camp_id){
        $camp = Camp::find($camp_id);
        return view('backend.camp.addBatch', ["camp" => $camp]);
    }

    public function showBatch($camp_id){
        $camp = Camp::find($camp_id);
        $batches = $camp->batches;
        $num_applicants = array();
        foreach($batches as $batch) {
            $num_applicants[$batch->id] = \DB::table('applicants')->where('batch_id',$batch->id)->count();
        }
        return view('backend.camp.batchList', compact('camp', 'batches','num_applicants'));
    }

    public function modifyBatch(Request $request, $camp_id, $batch_id){
        $formData = $request->toArray();
        $batch = Batch::find($batch_id);
        $batch->update($formData);
        $campName = Camp::find($camp_id)->abbreviation;
        if ($request->vbatch_id) {
            BatchVbatchXref::updateOrCreate(["batch_id" => $batch_id], ["vbatch_id" => $request->vbatch_id]);
        }
        \Session::flash('message', $campName . " " . $batch->name . " 修改成功。");
        return redirect()->route("showBatch", $camp_id);
    }

    public function showModifyBatch($camp_id, $batch_id){
        $camp = Camp::find($camp_id);
        $batch = Batch::find($batch_id);
        $vbatches = null;
        if (!$batch->isVbatch) {
            $vbatches = $camp->vcamp?->batches ?? null;
        }
        return view('backend.camp.modifyBatch', compact("camp", "batch", "vbatches"));
    }

    public function removeBatch(Request $request){
        $result = \App\Models\Batch::find($request->batch_id)->delete();
        if($result){
            \Session::flash('message', "梯次刪除成功。");
            return back();
        }
        else{
            \Session::flash('error', "梯次刪除失敗。");
            return back();
        }
    }

    public function addDSLink(Request $request, $camp_id){
        $formData = $request->toArray();
        $is_this_camp = false;
        if ($formData['urltable_type'] == 'Camp') {
            $is_this_camp = ($formData['urltable_id'] == $camp_id)? true:false;
        }
        else if ($formData['urltable_type'] == 'Batch') {
            $batch = Batch::find($formData['urltable_id']);
            if ($batch) {
                $is_this_camp = ($batch->camp->id == $camp_id)? true:false;
            } else {
                \Session::flash('error', " 找不到梯次，DSLink新增失敗。");
                return redirect()->route("showAddDSLink", $camp_id);
            }
        }
        else if ($formData['urltable_type'] == 'CampOrg') {
            $org = CampOrg::find($formData['urltable_id']);
            if ($org) {
                $is_this_camp = ($org->camp_id == $camp_id)? true:false;
            } else {
                \Session::flash('error', " 找不到職務，DSLink新增失敗。");
                return redirect()->route("showAddDSLink", $camp_id);
            }
        }
        else {  //$formData['urltable_type'] == 'Applicant'
            $applicant = Applicant::find($formData['urltable_id']);
            if ($applicant) {
                $is_this_camp = ($applicant->camp->id == $camp_id)? true:false;
            } else {
                \Session::flash('error', " 找不到報名者，DSLink新增失敗。");
                return redirect()->route("showAddDSLink", $camp_id);
            }
        }
        if ($is_this_camp) {
            $formData['urltable_type'] = 'App\\Models\\' . $formData['urltable_type'];
            DynamicStat::create($formData);
            \Session::flash('message', " DSLink新增成功。");
            return redirect()->route("showAddDSLink", $camp_id);
        } else {
            \Session::flash('error', " 非屬此營隊，DSLink新增失敗。");
            return redirect()->route("showAddDSLink", $camp_id);
        }
    }
    public function queryDSLink(Request $request, $camp_id){
        $formData = $request->toArray();
        $is_this_camp = false;
        if($formData['urltable_type'] == 'Camp') {
            $is_this_camp = ($formData['urltable_id'] == $camp_id)? true:false;
        }
        else if ($formData['urltable_type'] == 'Batch') {
            $batch = Batch::find($formData['urltable_id']);
            $is_this_camp = ($batch->camp->id == $camp_id)? true:false;
        }
        else if ($formData['urltable_type'] == 'CampOrg') {
            $org = CampOrg::find($formData['urltable_id']);
            $is_this_camp = ($org->camp_id == $camp_id)? true:false;
        }
        else {  //$formData['urltable_type'] == 'Applicant'
            $applicant = Applicant::find($formData['urltable_id']);
            $is_this_camp = ($applicant->camp->id == $camp_id)? true:false;
        }
        if ($is_this_camp) {
            $urltable_type = 'App\\Models\\'.$request->urltable_type;
            $urltable_id = $request->urltable_id;
            $ds = DynamicStat::select('dynamic_stats.*')
            ->where('urltable_id', $urltable_id)
            ->where('urltable_type', $urltable_type)
            ->first();
            if($ds==null) {
                \Session::flash('message', " 查無資料。");
                return redirect()->route("showAddDSLink", $camp_id);
            } else {
                $is_show = 1;
                //replace App\Models\XXX with XXX
                $ds->urltable_type = $request->urltable_type;
                return view('backend.other.addDSLink', compact("camp_id", "ds", "is_show"));
            }
        } else {
            \Session::flash('message', " 非屬此營隊，DSLink查詢失敗。");
            return redirect()->route("showAddDSLink", $camp_id);
        }
    }
    public function showAddDSLink($camp_id){
        return view('backend.other.addDSLink', compact("camp_id"));
    }
    public function modifyDSLink(Request $request, $camp_id){
        $formData = $request->toArray();
        $is_this_camp = false;
        if($formData['urltable_type'] == 'Camp') {
            $is_this_camp = ($formData['urltable_id'] == $camp_id)? true:false;
        }
        else if ($formData['urltable_type'] == 'Batch') {
            $batch = Batch::find($formData['urltable_id']);
            $is_this_camp = ($batch->camp->id == $camp_id)? true:false;
        }
        else if ($formData['urltable_type'] == 'CampOrg') {
            $org = CampOrg::find($formData['urltable_id']);
            $is_this_camp = ($org->camp_id == $camp_id)? true:false;
        }
        else {  //$formData['urltable_type'] == 'Applicant'
            $applicant = Applicant::find($formData['urltable_id']);
            $is_this_camp = ($applicant->camp->id == $camp_id)? true:false;
        }
        if ($is_this_camp) {
            $formData['urltable_type'] = 'App\\Models\\' . $formData['urltable_type'];
            $ds = DynamicStat::find($formData['ds_id']);
            $ds->update($formData);
            \Session::flash('message', " DSLink修改成功。");
            return redirect()->route("showAddDSLink", $camp_id);
        } else {
            \Session::flash('message', " 非屬此營隊，DSLink修改失敗。");
            return redirect()->route("showAddDSLink", $camp_id);
        }
    }

    /**
     * event_admin 與其可管理的營隊類型（僅限 superuser，由 admin middleware 檢查）
     */
    public function showEventAdmins(){
        $eventAdmins = \App\Models\User::where('permission', 'event_admin')->orderBy('name')->get(['id', 'name', 'email']);
        $scopes = \App\Models\UserCampTableScope::whereIn('user_id', $eventAdmins->pluck('id'))->get()->groupBy('user_id');
        // 只列主營隊類型，義工營會自動包含
        $campTables = Camp::query()->distinct()->orderBy('table')->pluck('table')
            ->filter(fn ($table) => CampManageAccessService::vcampTableOf($table))->values();
        return view('backend.user.eventAdmins', compact('eventAdmins', 'scopes', 'campTables'));
    }

    public function saveEventAdmin(Request $request){
        $request->validate([
            'user' => 'required|string',
            'camp_tables' => 'array',
            'camp_tables.*' => 'string|max:255',
        ]);

        $keyword = trim($request->input('user'));
        $user = \App\Models\User::where('email', $keyword)
            ->when(ctype_digit($keyword), fn ($query) => $query->orWhere('id', (int) $keyword))
            ->first();
        if (!$user) {
            \Session::flash('error', "找不到使用者「{$keyword}」，請輸入 Email 或使用者 ID。");
            return back()->withInput();
        }
        if ($user->permission && $user->permission !== 'event_admin') {
            \Session::flash('error', "{$user->name} 目前的身分是 {$user->permission}，不能設為 event_admin。");
            return back()->withInput();
        }

        $tables = collect($request->input('camp_tables', []))
            ->filter(fn ($table) => CampManageAccessService::vcampTableOf($table))
            ->unique()->values();

        \DB::transaction(function () use ($user, $tables) {
            $user->permission = 'event_admin';
            $user->save();
            \App\Models\UserCampTableScope::where('user_id', $user->id)->whereNotIn('camp_table', $tables)->delete();
            foreach ($tables as $table) {
                \App\Models\UserCampTableScope::firstOrCreate(['user_id' => $user->id, 'camp_table' => $table]);
            }
        });

        \Session::flash('message', "已設定 {$user->name} 為 event_admin，可管理：" . ($tables->isEmpty() ? '（無）' : $tables->implode('、')));
        return redirect()->route('showEventAdmins');
    }

    public function removeEventAdmin(Request $request){
        $user = \App\Models\User::where('permission', 'event_admin')->find($request->input('user_id'));
        if (!$user) {
            \Session::flash('error', "找不到該 event_admin。");
            return back();
        }

        \DB::transaction(function () use ($user) {
            $user->permission = null;
            $user->save();
            \App\Models\UserCampTableScope::where('user_id', $user->id)->delete();
        });

        \Session::flash('message', "已移除 {$user->name} 的 event_admin 身分。");
        return redirect()->route('showEventAdmins');
    }
}
