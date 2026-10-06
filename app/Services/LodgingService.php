<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\Batch;
use App\Models\Camp;
use App\Models\CampCustomOption;
use App\Models\Lodging;
use Carbon\Carbon;

class LodgingService
{
    private ApplicantService $applicantService;
    private CampDataService $campDataService;

    public function __construct(ApplicantService $applicantService, CampDataService $campDataService)
    {
        $this->applicantService = $applicantService;
        $this->campDataService = $campDataService;
    }

    public function updateApplicantLodging(Applicant $applicant, Camp $camp, $roomType, $nights = 1, $currencyCode = null)
    {
        $lodging = $applicant->lodging ?: new Lodging(['applicant_id' => $applicant->id]);

        // 基準幣別費率（梯次特定費率）
        $fare_room = $this->getLodgingFare($camp, $applicant->created_at, $applicant->batch_id);
        $fare_std  = $fare_room[$roomType] ?? 0;

        // 以資料庫的幣別清單核對，找不到就退回基準幣別，不信任前端傳來的值
        $currencies = $camp->currencies;
        $curr = $currencies->firstWhere('code', $currencyCode)
            ?? $currencies->firstWhere('pivot.is_std', 1)
            ?? $currencies->first();

        $curr_id    = $curr->id ?? 1;
        $curr_code  = $curr->code ?? 'TWD';
        $curr_xrate = (float) ($curr->pivot->xrate_to_std ?? 1);

        $lodging->room_type         = $roomType;
        $lodging->nights            = $nights ?? 1;
        $lodging->fare              = round($fare_std * $curr_xrate);   // 換算後金額
        $lodging->fare_currency_id  = $curr_id;                         // 使用者選的幣別
        $lodging->fare_xrate_to_std = $curr_xrate;                      // 使用者選的幣別
        $lodging->fare_std          = $fare_std;                        // 基準幣別金額
        $lodging->save();

        return $lodging;
    }

    /**
     * 取得房間費率設定
     */
    public function getLodgingFare(Camp $camp, Carbon $date, $batchId = null)
    {
        // ------------------------------------------------------------------
        // 1. 優先從 camp_custom_options 撈取
        // ------------------------------------------------------------------
        // 定義住宿費相關的 type 標籤清單
        $possibleTypes = ['lodgingUSD', 'lodgingNTD', 'fare_room', 'lodging'];

        $dbFareMap = CampCustomOption::getFareMap($camp->id, $batchId, $possibleTypes);

        if (!empty($dbFareMap)) {
            return $dbFareMap;
        }

        // ------------------------------------------------------------------
        // 2. 降級備援機制 (Fallback): 讀取原本的 config 檔與早鳥邏輯
        // ------------------------------------------------------------------
        $campTable = $camp->table;
        $date = $date->startOfDay();	//確保日期比較只考慮日期部分
	// 取得費率設定 (這部分邏輯建議也可以封裝)
        $fare_room = config('camps_payments.fare_room.' . $campTable) ?? [];
        $fare_room_early_bird = config('camps_payments.fare_room.' . $campTable . '_early_bird') ?? [];
        $fare_room_discount = config('camps_payments.fare_room.' . $campTable . '_discount') ?? [];

        if ($campTable == "nycamp") {
            //早鳥價<優惠價<正常價
            if ($camp->early_bird_last_day && $date->lte($camp->early_bird_last_day)) {
                $fare_room = $fare_room_early_bird;
            } elseif ($camp->discount_last_day && $date->lte($camp->discount_last_day)) {
                $fare_room = $fare_room_discount;
            }
        } elseif ($campTable == "utcamp") {
            //優惠價：兩人同行，比早鳥更優惠
            if ($camp->early_bird_last_day && $date->lte($camp->early_bird_last_day)) {
                $fare_room = $fare_room_early_bird + $fare_room_discount;
            } elseif ($camp->discount_last_day && $date->lte($camp->discount_last_day)) {
                $fare_room = $fare_room + $fare_room_discount;
            }
        } elseif ($camp->has_early_bird && $date->lte($camp->early_bird_last_day)) {
            //僅有早鳥價
            $fare_room = $fare_room_early_bird;
        }

        return $fare_room;
    }
}
