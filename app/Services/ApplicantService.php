<?php

namespace App\Services;

use App\Models\Applicant;
use Carbon\Carbon;
use App\Services\CampDataService;
use App\Services\LodgingService;
use App\Services\TrafficService;
use Enums\Gender;
use Enums\AttendStatus;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Log; // 如果需要記錄錯誤日誌

class ApplicantService
{
    private CampDataService $campDataService;

    public function __construct(CampDataService $campDataService)
    {
        $this->campDataService = $campDataService;
        return;
    }

    public function Mandarization($applicant)
    {
        switch ($applicant->gender) {
            case "M":
                $applicant->gender = "男";
                break;
            case "F":
                $applicant->gender = "女";
                break;
        }
        return $applicant;
    }

    public function convertFormat($title_data, $camp)
    {
        //匯入報名表時，將各種人理解的內容轉成要寫入database的內容
        //$regions = $camp->regions;  //valid regions
        foreach ($title_data as $key => $value) {
            if ($key == 'gender') {
                switch ($value) {
                    case "男":
                        $title_data[$key] = "M";
                        break;
                    case "女":
                        $title_data[$key] = "F";
                        break;
                    case "非常規":
                        $title_data[$key] = "NC";
                        break;
                    default:
                        $title_data[$key] = "NS";
                        break;
                }
            }
            if ($key == 'portrait_agree' || $key == 'profile_agree') {
                switch ($value) {
                    case "同意":
                        $title_data[$key] = "1";
                        break;
                    case "不同意":
                        $title_data[$key] = "0";
                        break;
                }
            }
            if ($key == 'region') {
                $region_found = $camp->regions->where('name', $value)->first();
                if ($region_found) {
                    $title_data['region_id'] = $region_found->id;
                }
                //otherwise, leave it null
            }
        }
        return $title_data;
    }

    public function groupAndNumberSeperator($admittedSN)
    {
        $group = substr($admittedSN, 0, 3);
        $number = substr($admittedSN, 3, strlen($admittedSN));
        return compact('group', 'number');
    }

    /**
     * 取得報名者完整資料
     *
     * @param 營隊 ID
     * @param 營隊資料表
     * @param 報名者 ID
     * @param 報名者組別
     * @param 報名者座號
     * @return \App\Models\Applicant
     */
    public function fetchApplicantData($camp_id, $table, $idOrName = null, $group = null, $number = null)
    {
        // 🚀 【重大優化】利用新欄位 camp_id 直球對決，移除原本對 batches 和 camps 的龐大 JOIN
        $applicant = Applicant::select('applicants.*', $table . '.*')
            ->join($table, 'applicants.id', '=', $table . '.applicant_id')
            ->where('applicants.camp_id', $camp_id) // 完美啟動 idx_camp_active_applicants 複合索引！
            ->where(function ($query) use ($idOrName, $group, $number) {
                if ($idOrName) {
                    $query->where('applicants.id', $idOrName);
                    $query->orWhere('applicants.name', 'like', '%' . $idOrName . '%');
                }
                if ($group && $number) {
                    $query->orWhere(function ($query) use ($group, $number) {
                        $query->where('group_legacy', 'like', $group);
                        $query->where('number_legacy', 'like', $number);
                    });
                    $query->orWhere(function ($query) use ($group, $number) {
                        $query->whereHas('groupRelation', function ($query) use ($group) {
                            $query->where('alias', 'like', $group);
                        });
                        $query->whereHas('numberRelation', function ($query) use ($number) {
                            $query->where('number', 'like', $number);
                        });
                    });
                }
            })->withTrashed()
            ->first();
        if ($applicant) {
            $applicant->id = $applicant->applicant_id;
        }
        return $applicant;
    }

    /**
     * 依據指定條件與營隊資訊，通用查找學員（包含預加載關聯與軟刪除資料）
     *
     * @param array $criteria 查詢條件陣列，例如 ['name' => '張三', 'email' => 'abc@test.com']
     * @param object|array $campInfo 營隊資訊物件或陣列，必須包含 id 和 table 屬性
     * @return Applicant|null
     */
    public function findApplicantWithCriteria(array $criteria, $campInfo)
    {
        // 1. 動態取得營隊主表名稱與營隊 ID
        $campId = is_array($campInfo) ? $campInfo['id'] : $campInfo->id;
        $campTable = is_array($campInfo) ? $campInfo['table'] : $campInfo->table;

        // 2. 建立基礎查詢並預加載標準關聯（包含動態的營隊主表）
        $query = Applicant::with([$campTable, 'batch', 'lodging', 'traffic'])
            ->where('camp_id', $campId)
            ->withTrashed();

        // 3. 動態將傳入的條件（如 name, email 等）加入查詢中
        foreach ($criteria as $column => $value) {
            if (!is_null($value)) {
                $query->where($column, $value);
            }
        }

        // 4. 回傳查到的第一筆資料（若無則回傳 null）
        return $query->first();
    }
    
    /**
     * 取得並清理申請人資料
     * * @param int|Applicant $applicantOrId 傳入 ID 或是已經查出來的 Applicant 物件
     * @param string $campTable
     * @param string|null $name
     */
    public function getApplicantData($applicantOrId, $campTable, $name = null)
    {
        try {
            // 【核心優化】如果傳進來的是物件，就直接使用，不再查資料庫！
            if ($applicantOrId instanceof Applicant) {
                $applicant = $applicantOrId;
                
                // 確保關聯已經被載入（如果 Controller 沒載入，這裡補載入，只查關聯，不查主表）
                $applicant->loadMissing([$campTable, 'batch', 'lodging', 'traffic']);
            } else {
                // 如果傳進來的是 ID，才去查資料庫
                $query = Applicant::with([$campTable, 'batch', 'lodging', 'traffic'])->withTrashed();
                if ($name) {
                    $query->where('name', $name);
                }
                $applicant = $query->findOrFail($applicantOrId);
            }

            // --- 底下維持你原本精妙的資料處理邏輯 ---
            $applicant->offsetUnset('files'); 

            $mergedData = array_merge(
                $applicant->toArray(),
                $applicant->$campTable ? $applicant->$campTable->toArray() : [],
                $applicant->lodging ? $applicant->lodging->toArray() : [],
                $applicant->traffic ? $applicant->traffic->toArray() : []
            );

            $mergedData['applicant_id'] = $applicant->id; // 改用 $applicant->id
            $mergedData[$campTable.'_id'] = $applicant->$campTable ? $applicant->$campTable->id : null;
            $mergedData['lodging_id'] = $applicant->lodging ? $applicant->lodging->id : null;
            $mergedData['traffic_id'] = $applicant->traffic ? $applicant->traffic->id : null;

            unset($mergedData[$campTable], $mergedData['lodging'], $mergedData['traffic']);

            $applicant_data = json_encode($mergedData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $applicant_data = str_replace(["\r", "\n", "\t"], "", $applicant_data);
            $applicant_data = addslashes($applicant_data);

            return [$applicant, $applicant_data];

        } catch (ModelNotFoundException $e) {
            // 狀況 A：找不到該申請人資料
            Log::warning("找不到報名者. ID: {$applicantId}, Name: {$name}");         
            throw $e; 
        } catch (\Throwable $e) {
            // 狀況 B：其他任何非預期的系統錯誤（如語法錯誤、資料庫斷線等）
            Log::error("系統錯誤: " . $e->getMessage());
            throw new \Exception("資料處理失敗，請稍後再試");
        }
    }

    public function checkIfPaidEarlyBird($applicant)
    {
        $is_admitted = $applicant->is_admitted?? false;
        // 須為已錄取
        // 如果已錄取，或營隊有早鳥且報名者已付清款項，則跳過
        if ($is_admitted || ($applicant->batch->camp->has_early_bird && ($applicant->fee - $applicant->deposit <= 0))) {
            return $applicant;
        }
        // 快樂營其他(無論有無早鳥)，僅檢查報名者是否錄取，未錄取表示未繳費完成，則填入繳費資料
        elseif ($applicant->batch->camp->table == "hcamp" && !$is_admitted) {
            $applicant = $this->fillPaymentData($applicant);
        }
        // 其他(無論有無早鳥)，僅檢查報名者是否錄取，已錄取則填入繳費資料
        elseif ($applicant->batch->camp->table != "hcamp" && $is_admitted) {
            $applicant = $this->fillPaymentData($applicant);
        }
        return $applicant;
    }

    /**
     * 錄取後，自動生成轉帳資料
     *
     * 使用 Camp 的 set_fee 和 set_payment_deadline，
     * 由 Camp model 本身判斷該提取早鳥價或原價
     *
     * @param 一個報名者 model
     * @param 營隊完整資料
     * @return 一個報名者 model
     */
    public function fillPaymentData($candidate)
    {
        if (!config('camps_payments.' . $candidate->batch->camp->table)) {
            return $candidate;
        }
        $data = array_merge(config('camps_payments.general'), config('camps_payments.' . $candidate->batch->camp->table));
        $startdate = Carbon::createFromFormat('Y-m-d', $candidate->batch->camp->payment_startdate ?? "2011-00-00");
        $deadline = Carbon::createFromFormat('Y-m-d', $candidate->batch->camp->set_payment_deadline ?? "2011-00-00");
        $startdate1 = sprintf("%02d%02d", $startdate->month, $startdate->day);
        //"西元年-2011" = 民國年後兩碼
        $deadline1 = sprintf("%02d%02d%02d", $deadline->year - 2011, $deadline->month, $deadline->day);
        $data["應繳日期"] = $startdate1;
        $data["繳費期限"] = $deadline1;
        $data["銷帳編號"] = $data["銷帳流水號前1碼"] . str_pad($candidate->id, 5, '0', STR_PAD_LEFT);
        if ($candidate->batch->camp->table == "ycamp") {
            // todo: 應釐清學員的 fee 和交通的 fare 之間的差別
            $candidate->fee = $candidate->traffic?->fare ?? 0;
        } elseif ($candidate->batch->camp->table == "ceocamp" || $candidate->batch->camp->table == "utcamp") {
            // todo: 應釐清學員的 fee 和交通的 fare 之間的差別
            $candidate->fee = $candidate->lodging?->fare ?? 0;
        } else {
            $candidate->fee = $candidate->batch->camp->set_fee ?? 0;
        }
        $paymentFlow = new PaymentflowService($data);
        $candidate->store_first_barcode = $paymentFlow->getStoreFirstBarcode();
        $candidate->store_second_barcode = $paymentFlow->getStoreSecondBarcode();
        $candidate->store_third_barcode = $paymentFlow->getStoreThirdBarcode($candidate->fee);
        $candidate->bank_second_barcode = $paymentFlow->getBankSecondBarcode();
        $candidate->bank_third_barcode = $paymentFlow->getBankThirdBarcode($candidate->fee);
        $candidate->deposit = $candidate->deposit == null || $candidate->deposit == 0 ? 0 : $candidate->deposit;
        $candidate->save();
        return $candidate;
    }

    public function checkPaymentStatus($applicant)
    {
        if (!$applicant || $applicant->deleted_at) {
            return null;
        }
        $applicant->showCheckInInfo = 0;
        if ($applicant->deposit == 0) {
            $status = "未繳費";
            if ($applicant->fee == 0) {
                $status = "無費用";
                $applicant->showCheckInInfo = 1;
            }
        } elseif ($applicant->fee - $applicant->deposit > 0) {
            $status = "已繳部分金額，尚餘" . ($applicant->fee - $applicant->deposit) . "元";
            $applicant->showCheckInInfo = 1;
        } elseif ($applicant->fee - $applicant->deposit < 0) {
            $status = "已繳費，溢繳" . ($applicant->deposit - $applicant->fee) . "元";
            $applicant->showCheckInInfo = 1;
        } else {
            $status = "已繳費";
            $applicant->showCheckInInfo = 1;
        }
        $applicant->payment_status = $status;
        return $applicant;
    }

    public function retriveApplicantForSignInSignOut($request)
    {
        // $group = substr($request->admitted_no, 0, 3);
        // $number = substr($request->admitted_no, 3, 2);
        // todo: 2024/12/15 後需回復
        $request->name = $request->name ?? '0'; //if null, assign some value
        $request->mobile = $request->mobile ?? 'x'; //if null, assign some value
        // $applicant =  Applicant::where('is_admitted', 1)
        //                     ->where(function($query) use ($request){
        // todo: 2024/12/15 後需回復
        $applicant =  Applicant::where(function ($query) use ($request) {
            // $query->where('id', $request->query_str)
            // ->orWhere('name', 'like', '%' . $request->query_str . '%')
            $query->where('name', 'like', $request->name)
                  // todo: 2024/12/15 後需回復
                  ->orWhere(function ($query) use ($request) {
                      $query->where(\DB::raw("replace(mobile, '-', '')"), 'like', '%' . $request->mobile . '%')
                           ->orWhere(\DB::raw("replace(mobile, '(', '')"), 'like', '%' . $request->mobile . '%')
                           ->orWhere(\DB::raw("replace(mobile, ')', '')"), 'like', '%' . $request->mobile . '%')
                           ->orWhere(\DB::raw("replace(mobile, '（', '')"), 'like', '%' . $request->mobile . '%')
                           ->orWhere(\DB::raw("replace(mobile, '）', '')"), 'like', '%' . $request->mobile . '%');
                  });
        })
                            // ->where([['group', $group], ['number', $number]])
                            ->orderBy('id', 'desc')->first();
        if ($applicant?->batch?->camp?->needed_to_reply_attend) {
            return $applicant->where('is_attend', 1)->first();
        }
        return $applicant;
    }

    public function generatesSignMessage($applicant)
    {
        $signInSignOutObject = $applicant->batch->canSignNow();
        if ($signInSignOutObject) {
            $str = $signInSignOutObject->isSignIn() ? "簽到" : "簽退";
            $message = [
                'status' => true,
                'message' => '可' . $str . '時間：' . Carbon::parse($signInSignOutObject->start)->format('H:i') . ' ~ ' . Carbon::parse($signInSignOutObject->end)->format('H:i')
            ];
        } else {
            $message = [
                'status' => false,
                'message' => '目前非簽到/退時間，僅供檢視記錄'
            ];
        }

        return [$message, $signInSignOutObject];
    }

    public function updateApplicantXcamp(Applicant $applicant, $campTable, $formData)
    {
        $xcamp = $applicant->$campTable;    //applicant's associated camp data
        //處理檔案及圖片
        try {
            $disk = \Storage::disk('local');
            $path = 'avatars/';
            if (request()->hasFile('avatar')) {
                $file = request()->file('avatar');
                $name = $file->hashName();
            }
            if (request()->hasFile('avatar_re')) {
                $file = request()->file('avatar_re');
                $name = $file->hashName();
            }

            if ($file ?? false) {
                $disk->put($path, $file);
                $image = Image::make(storage_path($path . $name))->resize(800, null, function ($constraint) {
                    $constraint->aspectRatio();
                });
                $image->save(storage_path($path . $name));
                $formData['avatar'] = $path . $name;
            }
        } catch (\Throwable $e) {
            logger($e);
        }

        $updatedApplicant = \DB::transaction(function () use ($applicant, $xcamp, $formData) {
            if (isset($formData['is_educating'])) {
                if ($formData['is_educating'] == 0) {
                    $formData['school_or_course'] = '';
                    $formData['subject_teaches'] = '';
                }
            }
            $applicantFillable = $applicant->getFillable();
            $campFillable = $xcamp->getFillable();
            $applicantData = array();
            $campData = array();
            foreach ($formData as $key => $value) {
                if (in_array($key, $applicantFillable)) {
                    $applicantData[$key] = $value;
                }
                if (in_array($key, $campFillable)) {
                    $campData[$key] = $value;
                }
            }
            $applicant->fill($applicantData);
            $applicant->save();
            $xcamp->fill($campData);
            $xcamp->save();

            return $applicant; // 回傳更新後的物件
        });
        return $updatedApplicant; // 回傳更新後的物件
    }
}
