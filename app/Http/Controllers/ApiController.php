<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Camp;

class ApiController extends Controller
{
    //
    public function sendCampData(Request $request)
    {
        // 2026-11-14：key 原本寫死在程式裡（2022-03 起就在 git 中），視為已外洩，改由 .env 的 CAMP_DATA_API_KEY 設定；
        // 沒有設定時此端點關閉。回傳整營報名者（含報到、簽到）資料，key 請只交給需要的系統
        $key = config('services.camp_data_api.key');
        if (!$key || !is_string($request->key) || !hash_equals($key, $request->key)) {
            return response()->json(['error' => '401'], 401);
        }
        $camp = $request->camp;
        $year = $request->year;

        $data = Camp::with('batches', 'batches.applicants', 'batches.applicants.checkInData', 'batches.applicants.signData')
                        ->where('table', $camp)
                        ->where(function($query) use ($year) {
                            $query->where('fullName', 'like', '%' . $year . '%')
                                ->orWhere('registration_start', 'like', '%' . $year . '%');
                        })->first();

        return response()->json([
            'camp' => $camp,
            'year' => $year,
            'data' => $data
        ]);
    }
}
