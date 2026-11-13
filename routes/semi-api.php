<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SemiApiController;

/*
|--------------------------------------------------------------------------
| Semi-API Routes（前綴 /semi-api，web middleware）
|--------------------------------------------------------------------------
|
| 後台頁面以 AJAX 呼叫的端點。2026-11-14 之前整組都不需登入，現在一律要登入，
| 各端點再檢查營隊工作人員與權限（見 SemiApiController）。
|
| 移除的端點（前端已不使用）：
| - getFieldData：前台表單回填改讀 window.applicant_data；原本可依 id 讀任何報名者的任何欄位
| - getCampVolunteers：回傳整個義工營的報名資料
| - groupCreation：替任一營隊建組別（組別編製頁已取代）
| - setGroupOrg：指派職務並建立帳號（改由後台 connectVolunteerToUser 處理）
| - getBatch、getCampData：與 routes/api.php 重複
|
*/
Route::middleware('auth')->group(function () {
    Route::get('getBatchGroups', [SemiApiController::class, 'getBatchGroups']);
    Route::post('setGroup', [SemiApiController::class, 'setGroup']);
    Route::post('setCarer', [SemiApiController::class, 'setCarer']);
    Route::get('getCampOrganizations', [SemiApiController::class, 'getCampOrganizations']);
    Route::get('getCampPositions', [SemiApiController::class, 'getCampPositions']);
    Route::post('getOrgSel', [SemiApiController::class, 'getOrgSel']);
    Route::post('getPermissionSel', [SemiApiController::class, 'getPermissionSel']);
});
