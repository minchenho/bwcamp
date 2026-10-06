<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\Batch;
use App\Models\Camp;
use App\Models\CampCustomOption;
use App\Models\Traffic;

class TrafficService
{
    private ApplicantService $applicantService;
    private CampDataService $campDataService;

    public function __construct(ApplicantService $applicantService, CampDataService $campDataService)
    {
        $this->applicantService = $applicantService;
        $this->campDataService = $campDataService;
    }

    // TrafficService.php 或 ApplicantService.php
    public function updateApplicantTraffic(Applicant $applicant, Camp $camp, $departFrom, $backTo, $currencyCode = 'TWD')
    {
        //$campTable = $camp->table;
        $traffic = $applicant->traffic ?: new Traffic(['applicant_id' => $applicant->id]);

        // 基準幣別費率（梯次特定費率）
        [$fare_depart_from, $fare_back_to] = $this->getTrafficFare($camp, $applicant->batch_id);
        $fare_std  = ($fare_depart_from[$departFrom] ?? 0) + ($fare_back_to[$backTo] ?? 0);

        // 以資料庫的幣別清單核對，找不到就退回基準幣別，不信任前端傳來的值
        $currencies = $camp->currencies;
        $curr = $currencies->firstWhere('code', $currencyCode)
            ?? $currencies->firstWhere('pivot.is_std', 1)
            ?? $currencies->first();

        $curr_id    = $curr->id ?? 1;
        $curr_code  = $curr->code ?? 'TWD';
        $curr_xrate = (float) ($curr->pivot->xrate_to_std ?? 1);

        $traffic->depart_from       = $departFrom;
        $traffic->back_to           = $backTo;
        $traffic->fare              = round($fare_std * $curr_xrate);   // 換算後金額
        $traffic->fare_currency_id  = $curr_id;                         // 使用者選的幣別
        $traffic->fare_xrate_to_std = $curr_xrate;                      // 使用者選的幣別
        $traffic->fare_std          = $fare_std;
        $traffic->save();

        // 更新付款資料
        //$applicant = $this->applicantService->fillPaymentData($applicant);
        //$applicant->save();

        //return [$applicant, $fare_depart_from, $fare_back_to]; // 回傳更新後的物件及費率設定
        return $traffic;
    }

    public function getTrafficFare(Camp $camp, $batchId = null)
    {
        // ------------------------------------------------------------------
        // 1. 優先從 camp_custom_options 撈取
        // ------------------------------------------------------------------
        // 定義住宿費相關的 type 標籤清單
        $possibleTypesDepartFrom = ['departFromUSD', 'departFromNTD'];
        $possibleTypesBackTo = ['backToUSD', 'backToNTD'];

        $dbFareMapDepartFrom = CampCustomOption::getFareMap($camp->id, $batchId, $possibleTypesDepartFrom);
        $dbFareMapBackTo = CampCustomOption::getFareMap($camp->id, $batchId, $possibleTypesBackTo);

        if (!empty($dbFareMapDepartFrom) || !empty($dbFareMapBackTo)) {
            return [$dbFareMapDepartFrom, $dbFareMapBackTo];
        }

        // ------------------------------------------------------------------
        // 2. 降級備援機制 (Fallback): 讀取原本的 config 檔與早鳥邏輯
        // ------------------------------------------------------------------

        // 取得費率設定 (這部分邏輯建議也可以封裝)
        $campTable = $camp->table;
        $fare_depart_from = config('camps_payments.fare_depart_from.' . $campTable) ?? [];
        $fare_back_to = config('camps_payments.fare_back_to.' . $campTable) ?? [];

        return [$fare_depart_from, $fare_back_to];
    }
}
