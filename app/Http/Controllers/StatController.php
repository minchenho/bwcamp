<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Traits\EmailConfiguration;

class StatController extends BackendController
{
    use EmailConfiguration;

    /**
     * 萬能區域撈取器（僅在第一次呼叫時查資料庫，之後直接讀記憶體）
     * 用法：在任何方法寫 $this->getSpecifiedRegions() 即可
     */
    protected function getSpecifiedRegions(): array
    {
        // 如果這輩子已經查過一次了，就直接吐回去，不重查資料庫
        if (!isset($this->specifiedRegionsCache)) {
            $this->specifiedRegionsCache = $this->camp && $this->camp->regions 
                ? $this->camp->regions->pluck('name')->toArray()
                : [];
        }

        return $this->specifiedRegionsCache;
    }

    /**
     * 年齡與年次 萬能通用統計 (完美結合 $applicant->age 屬性)
     *
     * @param string $mode 'range' 呈現年齡層區間 / 'year' 呈現出生年次(歲)
     * 年齡與年次 通用分區統計 (支援前端全區/分區切換)
     */
    public function ageStat($mode = 'range')
    {
        // 1. 撈取基礎數據 (包含 region)
        $applicantsData = Applicant::select(\DB::raw('applicants.birthyear, applicants.region, count(*) as total, MAX(applicants.id) as sample_id'))
            ->join($this->camp_table, 'applicants.id', '=', $this->camp_table . '.applicant_id')
            ->join('batches', 'batches.id', '=', 'applicants.batch_id')
            ->where('batches.camp_id', $this->camp_id)
            ->whereNull('applicants.deleted_at')
            ->groupBy('applicants.birthyear', 'applicants.region')
            ->get();

        $specifiedRegions = $this->getSpecifiedRegions();
        $currentYear = now()->year;

        // 2. 初始化定義區間 (只有 range 模式需要固定順序)
        $rangesOrder = [];
        if ($mode === 'range') {
            $rangesOrder = ['<=20', '21-30', '31-40', '41-50', '51-60', '61-70', '>70', '其它'];
        } else {
            // 年次模式：先收集所有出現過的精準年次標籤，確保等一下各分區的 key 順序完全一致
            $tempYears = [];
            foreach ($applicantsData as $r) {
                if (!empty($r->birthyear)) $tempYears[] = $r->birthyear;
            }
            $tempYears = array_unique($tempYears);
            sort($tempYears); // 由老到年輕排序
            foreach ($tempYears as $y) {
                $age = $currentYear - $y;   //只用year去計算年齡，有一點不精準。
                $rangesOrder[] = $y . '(' . $age . ')';
            }
            $rangesOrder[] = '其它';
        }

        // 3. 初始化各分區的暫存容器
        $rawGroupData = ['all' => [], 'other' => []];
        foreach ($specifiedRegions as $rName) { $rawGroupData[$rName] = []; }

        $totals = ['all' => 0, 'other' => 0];
        foreach ($specifiedRegions as $rName) { $totals[$rName] = 0; }

        // 預填容器確保順序一致
        foreach (array_merge(['all', 'other'], $specifiedRegions) as $gKey) {
            foreach ($rangesOrder as $label) { $rawGroupData[$gKey][$label] = 0; }
        }

        // 4. 開始分流計算
        foreach ($applicantsData as $record) {
            $birthyear = $record->birthyear;
            $region = $record->region ? trim($record->region) : '';
            $count = intval($record->total);
            $totals['all'] += $count;

            // 計算對應的標籤
            if (empty($birthyear)) {
                $label = '其它';
            } else {
                $age = $currentYear - $birthyear;   //只用year去計算年齡，有一點不精準。
                $label = $mode === 'range' 
                    ? ($age <= 20 ? '<=20' : 
                    ($age <= 30 ? '21-30' : 
                    ($age <= 40 ? '31-40' : 
                    ($age <= 50 ? '41-50' : 
                    ($age <= 60 ? '51-60' : 
                    ($age <= 70 ? '61-70' :
                    '>70'))))))
                    : $birthyear . '(' . $age . ')';
            }

            // 累加
            $rawGroupData['all'][$label] += $count;
            if (in_array($region, $specifiedRegions)) {
                $rawGroupData[$region][$label] += $count;
                $totals[$region] += $count;
            } else {
                $rawGroupData['other'][$label] += $count;
                $totals['other'] += $count;
            }
        }

        // 5. 組裝 Google Chart 格式
        $chartCols = [
            ['id' => 'age_key', 'label' => $mode === 'range' ? '年齡級距' : '年次(歲)', 'type' => 'string'],
            ['id' => 'people', 'label' => '人數', 'type' => 'number'],
            ['id' => 'annotation', 'role' => 'annotation', 'type' => 'number']
        ];

        $displayConfig = array_merge(['all' => '全區'], array_combine($specifiedRegions, $specifiedRegions), ['other' => '其它區域']);
        $chartCollection = [];

        foreach ($displayConfig as $key => $labelText) {
            $rows = [];
            foreach ($rawGroupData[$key] as $labelName => $count) {
                // 年次模式下，如果全區和分區該項目都是 0 就不塞入，優化畫面
                if ($mode === 'year' && $rawGroupData['all'][$labelName] === 0) continue;

                $rows[] = ['c' => [['v' => $labelName], ['v' => $count], ['v' => $count]]];
            }
            $chartCollection[$key] = [
                'label' => $labelText,
                'total' => $totals[$key],
                'data'  => ['cols' => $chartCols, 'rows' => $rows]
            ];
        }

        $chartCollectionJson = json_encode($chartCollection);
        $specifiedKeys = $specifiedRegions;
        $title1 = $mode === 'range' ? '年齡級距' : '年次(歲)';

        // 💡 傳遞給前端的新變數名
        return view('backend.statistics.ageCustom', compact('chartCollectionJson', 'specifiedKeys', 'title1'));
}

    public function ageRangeStat() { return $this->ageStat('range'); }
    public function birthyearStat() { return $this->ageStat('year'); }

    public function appliedDateStat() {
        // 1. 撈出所有日期與區域的報名統計
        $applicants = Applicant::select(\DB::raw('
                DATE_FORMAT(applicants.created_at, "%Y-%m-%d") as date, 
                applicants.region, 
                count(*) as total
            '))
            ->join($this->camp_table, 'applicants.id', '=', $this->camp_table . '.applicant_id')
            ->join('batches', 'batches.id', '=', 'applicants.batch_id')
            ->join('camps', 'camps.id', '=', 'batches.camp_id')
            ->where('camps.id', $this->camp_id)
            ->whereNull('applicants.deleted_at')
            ->groupBy('date', 'applicants.region')
            ->orderBy('date', 'asc') // 確保日期由舊到新，累加才會正確
            ->withTrashed()
            ->get();

        // 2. 取得該營隊綁定且排好序的指定分區清單
        $specifiedRegions = $this->getSpecifiedRegions();

        // 3. 初始化各分區的每日暫存容器 (以 date 為 key 方便稍後按日期累加)
        $rawGroupData = [
            'all'   => [],
            'other' => []
        ];
        foreach ($specifiedRegions as $rName) {
            $rawGroupData[$rName] = [];
        }

        // 4. 第一階段分流：將資料歸入對應區域的特定日期中
        foreach ($applicants as $record) {
            $date   = $record->date;
            $region = $record->region ? trim($record->region) : '';
            $count  = intval($record->total);

            if (empty($date)) continue;

            // 全區累加
            $rawGroupData['all'][$date] = ($rawGroupData['all'][$date] ?? 0) + $count;

            // 分區分流
            if (in_array($region, $specifiedRegions)) {
                $rawGroupData[$region][$date] = ($rawGroupData[$region][$date] ?? 0) + $count;
            } else {
                $rawGroupData['other'][$date] = ($rawGroupData['other'][$date] ?? 0) + $count;
            }
        }

        // 5. 定義圖表欄位格式
        $colsDaily = [
            ['id' => 'date', 'label' => '日期', 'type' => 'date'],
            ['id' => 'people', 'label' => '人數', 'type' => 'number'],
            ['id' => 'annotation', 'role' => 'annotation', 'type' => 'number']
        ];
        $colsAccu = [
            ['id' => 'date', 'label' => '日期', 'type' => 'date'],
            ['id' => 'people_accu', 'label' => '累計人數', 'type' => 'number'],
            ['id' => 'annotation', 'role' => 'annotation', 'type' => 'number']
        ];

        // 建立輸出設定順序
        $displayConfig = ['all' => '全區'];
        foreach ($specifiedRegions as $rName) {
            $displayConfig[$rName] = $rName;
        }
        $displayConfig['other'] = '其它區域';

        $chartCollection = [];

        // 6. 第二階段：對每個區域獨立跑日期排序，並計算該區專屬的累計值 (Accu)
        foreach ($displayConfig as $key => $labelText) {
            $dateArray = $rawGroupData[$key];
            ksort($dateArray); // 確保日期順序嚴格由舊到新

            $rowsDaily = [];
            $rowsAccu  = [];
            $runningTotal = 0; // 該分區專屬的累計計數器

            foreach ($dateArray as $dateStr => $count) {
                $year  = (int) substr($dateStr, 0, 4);
                $month = ((int) substr($dateStr, 5, 2)) - 1;
                $day   = (int) substr($dateStr, -2);
                
                $dateValue = "Date($year, $month, $day)";
                $runningTotal += $count;

                // 每日新增
                $rowsDaily[] = ['c' => [
                    ['v' => $dateValue],
                    ['v' => $count],
                    ['v' => $count]
                ]];

                // 每日累計
                $rowsAccu[] = ['c' => [
                    ['v' => $dateValue],
                    ['v' => $runningTotal],
                    ['v' => $runningTotal]
                ]];
            }

            $chartCollection[$key] = [
                'label' => $labelText,
                'total' => $runningTotal, // 該區最終總人數
                'daily' => ['cols' => $colsDaily, 'rows' => $rowsDaily],
                'accu'  => ['cols' => $colsAccu, 'rows' => $rowsAccu]
            ];
        }

        $chartCollectionJson = json_encode($chartCollection);
        $specifiedKeys = $specifiedRegions;

        return view('backend.statistics.appliedDate', compact('chartCollectionJson', 'specifiedKeys'));
    }

    public function favoredEventStat(){
        $applicants = Applicant::select(\DB::raw('ecamp.favored_event as event, count(*) as total'))
        ->join('batches', 'batches.id', '=', 'applicants.batch_id')
        ->join('camps', 'camps.id', '=', 'batches.camp_id')
        ->join('ecamp', 'ecamp.applicant_id', '=', 'applicants.id')
        ->where('camps.id', $this->camp_id)
        ->whereNull('applicants.deleted_at')
        ->groupBy('event')->get();        
        $rows = $applicants->count();
        $array = $applicants->toArray();

        $GChartData = array('cols'=> array(
                        array('id'=>'way','label'=>'管道','type'=>'string'),
                        array('id'=>'people','label'=>'人數','type'=>'number'),
                        array('id'=>'annotation','role'=>'annotation','type'=>'number')
                    ),
                    'rows' => array());
        //split            
        $events_all = array();
        $k = 0;
        for($i = 0; $i < $rows; $i++) {
            $record = $array[$i];
            if ($record['event'] == null) continue;
            $events_split = explode("||/",$record['event']);
            $events_split_cnt = count($events_split);
            for ($j = 0; $j < $events_split_cnt; $j++) {
                $events_all[$k]['event'] = $events_split[$j];
                $events_all[$k]['total'] = $record['total'];
                $k++;
            }
        }
       
        //combined
        sort($events_all);
        $events_all_cnt = count($events_all);

        $events_list = array();
        $events_list[0] = $events_all[0];
        $j = 0;
        for($i = 1; $i < $events_all_cnt; $i++) {
            $record = $events_all[$i];
            if ($events_list[$j]['event'] == $record['event']) {   //if same, add total
                $events_list[$j]['total'] += $record['total'];
            } else {    //if diff, create item
                $j++;
                $events_list[$j] = $record;
            }
        }

        $events_list_cnt = count($events_list);
        $total = 0 ;
        for($i = 0; $i < $events_list_cnt; $i ++) {
            $record = $events_list[$i];
            array_push($GChartData['rows'], array('c' => array(
                array('v' => $record['event']),
                array('v' => intval($record['total'])),
                array('v' => intval($record['total']))
            )));
            $total = $total + $record['total'];
        }

        $GChartData = json_encode($GChartData);

        return view('backend.statistics.favoredEvent', compact('GChartData','total','rows'));
    }

    public function genderStat() {
        // 1. 撈出所有性別與區域的統計數據
        $applicants = Applicant::select(\DB::raw('
                applicants.gender, 
                applicants.region, 
                count(*) as total
            '))
            ->join($this->camp_table, 'applicants.id', '=', $this->camp_table . '.applicant_id')
            ->join('batches', 'batches.id', '=', 'applicants.batch_id')
            ->join('camps', 'camps.id', '=', 'batches.camp_id')
            ->where('camps.id', $this->camp_id)
            ->whereNull('applicants.deleted_at')
            ->groupBy('applicants.gender', 'applicants.region')
            ->get();

        // 2. 取得該營隊綁定且排好序的指定分區清單
        $specifiedRegions = $this->getSpecifiedRegions();

        // 3. 初始化各分區的暫存容器 (以性別編碼 M/F/NC/NS/其它 作為 key)
        $rawGroupData = [
            'all'   => [],
            'other' => []
        ];
        foreach ($specifiedRegions as $rName) {
            $rawGroupData[$rName] = [];
        }

        $totals = [
            'all'   => 0,
            'other' => 0
        ];
        foreach ($specifiedRegions as $rName) {
            $totals[$rName] = 0;
        }

        // 4. 開始分流與累加資料
        foreach ($applicants as $record) {
            $record->setAppends([]); // 避免觸發額外的自動載入
            
            $gender = $record->gender ?: '其它';
            $region = $record->region ? trim($record->region) : '';
            $count  = intval($record->total);

            // (A) 全區累加
            $rawGroupData['all'][$gender] = ($rawGroupData['all'][$gender] ?? 0) + $count;
            $totals['all'] += $count;

            // (B) 依特定區域分流，其餘歸入其它
            if (in_array($region, $specifiedRegions)) {
                $rawGroupData[$region][$gender] = ($rawGroupData[$region][$gender] ?? 0) + $count;
                $totals[$region] += $count;
            } else {
                $rawGroupData['other'][$gender] = ($rawGroupData['other'][$gender] ?? 0) + $count;
                $totals['other'] += $count;
            }
        }

        // 5. 標準化為 Google Chart 格式
        $chartCols = [
            ['id' => 'gender_chn', 'label' => '性別', 'type' => 'string'],
            ['id' => 'people', 'label' => '人數', 'type' => 'number'],
            ['id' => 'annotation', 'role' => 'annotation', 'type' => 'number']
        ];

        // 建立一個空的臨時 Model 實例，用來動態調用內部的 genderChn 屬性
        $tempApplicant = new Applicant();

        // 設定輸出順序清單
        $displayConfig = ['all' => '全區'];
        foreach ($specifiedRegions as $rName) {
            $displayConfig[$rName] = $rName;
        }
        $displayConfig['other'] = '其它區域';

        $chartCollection = [];

        foreach ($displayConfig as $key => $labelText) {
            $dataArray = $rawGroupData[$key];
            
            $rows = [];
            foreach ($dataArray as $genderCode => $count) {
                
                // 【核心邏輯】只有在要顯示給 Google Chart 的時候，才透過 Model 的 Accessor 轉成中文
                if ($genderCode === '其它') {
                    $genderChn = '其它';
                } else {
                    $tempApplicant->gender = $genderCode;
                    $genderChn = $tempApplicant->gender_chn; // 自動調用 getGenderChnAttribute
                }

                $rows[] = ['c' => [
                    ['v' => $genderChn],
                    ['v' => $count],
                    ['v' => $count]
                ]];
            }

            $chartCollection[$key] = [
                'label' => $labelText,
                'total' => $totals[$key],
                'data'  => ['cols' => $chartCols, 'rows' => $rows]
            ];
        }

        $chartCollectionJson = json_encode($chartCollection);
        $specifiedKeys = $specifiedRegions;

        return view('backend.statistics.gender', compact('chartCollectionJson', 'specifiedKeys'));
    }

    public function countyStat() {
        // 1. 定義你想要的特定順序
        $customOrder = ['臺北市', '新北市', '基隆市', '桃園市', '新竹市', '新竹縣', '苗栗縣', '臺中市', '彰化縣', '南投縣', '雲林縣', '嘉義市', '嘉義縣', '臺南市', '高雄市', '屏東縣', '宜蘭縣', '花蓮縣', '臺東縣', '澎湖縣', '金門縣', '連江縣', '其它'];
        
        // 將陣列轉為 SQL FIELD 用的字串範例: "'台北市','新北市','基隆市'..."
        $orderString = "'" . implode("','", $customOrder) . "'";

        if ($this->camp_table == 'acamp' && $this->camp->year >= 2025) {
            $query = Applicant::select(\DB::raw('acamp.class_county as county, count(*) as total'));
        } else {
            $query = Applicant::select(\DB::raw('SUBSTRING(applicants.address, 1, 3) as county, count(*) as total'));
        }

        $applicants = $query->join($this->camp_table, 'applicants.id', '=', $this->camp_table . '.applicant_id')
            ->join('batches', 'batches.id', '=', 'applicants.batch_id')
            ->join('camps', 'camps.id', '=', 'batches.camp_id')
            ->where('camps.id', $this->camp_id)
            ->whereNull('applicants.deleted_at')
            ->groupBy('county')
            // 2. 使用 orderByRaw 進行自定義排序
            // FIELD(欄位名稱, '值1', '值2', ...) 如果不在清單內的會排在最前面或最後面
            ->orderByRaw(\DB::raw("FIELD(county, $orderString) = 0, FIELD(county, $orderString)")) ->get();

        // 後續處理...
        $total = 0;
        $GChartData = [
            'cols' => [
                ['id' => 'city', 'label' => '縣市', 'type' => 'string'],
                ['id' => 'people', 'label' => '人數', 'type' => 'number'],
                ['id' => 'annotation', 'role' => 'annotation', 'type' => 'number']
            ],
            'rows' => []
        ];

        foreach ($applicants as $record) {
            $record->setAppends([]); // 確保不計算 appends
            $label = $record->county ?: '其它';
            $count = intval($record->total);

            $GChartData['rows'][] = ['c' => [
                ['v' => $label],
                ['v' => $count],
                ['v' => $count]
            ]];
            $total += $count;
        }
        //dd($GChartData);
        $GChartData = json_encode($GChartData);
        return view('backend.statistics.county', compact('GChartData', 'total'));
    }

    public function batchesStat(){
        $applicants = Applicant::select(\DB::raw('batches.name as batch, count(*) as total'))
        ->join('batches', 'batches.id', '=', 'applicants.batch_id')
        ->join('camps', 'camps.id', '=', 'batches.camp_id')
        ->where('camps.id', $this->camp_id)
        ->whereNull('applicants.deleted_at')
        ->groupBy('batches.name')->get();
        $rows = $applicants->count(); 
        $array = $applicants->toArray();

        $i = 0 ;
        $total = 0 ;
        $GChartData = array('cols'=> array(
                        array('id'=>'batch','label'=>'梯次','type'=>'string'),
                        array('id'=>'people','label'=>'人數','type'=>'number'),
                        array('id'=>'annotation','role'=>'annotation','type'=>'number')
                    ),
                    'rows' => array());
        for($i = 0; $i < $rows; $i ++) {
            $record = $array[$i];
            array_push($GChartData['rows'], array('c' => array(
                array('v' => $record['batch'] == null ? '其它' : $record['batch']),
                array('v' => intval($record['total'])),
                array('v' => intval($record['total']))
            )));
            $total = $total + $record['total'];
        }
        $GChartData = json_encode($GChartData);

        return view('backend.statistics.batches', compact('GChartData',  'total'));
    }

    public function regionStat(){
        $applicants = Applicant::select(\DB::raw('applicants.region, count(*) as total'))
        ->join($this->camp_table, 'applicants.id', '=', $this->camp_table . '.applicant_id')
        ->join('batches', 'batches.id', '=', 'applicants.batch_id')
        ->join('camps', 'camps.id', '=', 'batches.camp_id')
        ->where('camps.id', $this->camp_id)
        ->whereNull('applicants.deleted_at')
        ->groupBy('applicants.region')->get();
        $rows = $applicants->count();
        $array = $applicants->toArray();

        $total = 0 ;
        $GChartData = array('cols'=> array(
                        array('id'=>'region','label'=>'區域','type'=>'string'),
                        array('id'=>'people','label'=>'人數','type'=>'number'),
                        array('id'=>'annotation','role'=>'annotation','type'=>'number')
                    ),
                    'rows' => array());
        for($i = 0; $i < $rows; $i ++) {
            $record = $array[$i];
            array_push($GChartData['rows'], array('c' => array(
                array('v' => $record['region']),
                array('v' => intval($record['total'])),
                array('v' => intval($record['total']))
            )));
            $total = $total + $record['total'];
        }
        $GChartData = json_encode($GChartData);

        return view('backend.statistics.region', compact('GChartData',  'total'));
    }

    public function schoolOrCourseStat(){
        $applicants = Applicant::select(\DB::raw('tcamp.school_or_course as school_or_course, count(*) as total'))
        ->join('batches', 'batches.id', '=', 'applicants.batch_id')
        ->join('camps', 'camps.id', '=', 'batches.camp_id')
        ->join('tcamp', 'tcamp.applicant_id', '=', 'applicants.id')
        ->where('camps.id', $this->camp_id)
        ->groupBy('tcamp.school_or_course')->get();
        $rows = $applicants->count();
        $array = $applicants->toArray();

        $i = 0 ;
        $total = 0 ;
        $GChartData = array('cols'=> array(
                        array('id'=>'school_or_course','label'=>'學程','type'=>'string'),
                        array('id'=>'people','label'=>'人數','type'=>'number'),
                        array('id'=>'annotation','role'=>'annotation','type'=>'number')
                    ),
                    'rows' => array());
        for($i = 0; $i < $rows; $i ++) {
            $record = $array[$i];
            array_push($GChartData['rows'], array('c' => array(
                array('v' => $record['school_or_course'] == null ? '其它' : $record['school_or_course']),
                array('v' => intval($record['total'])),
                array('v' => intval($record['total']))
            )));
            $total = $total + $record['total'];
        }
        $GChartData = json_encode($GChartData);

        return view('backend.statistics.schoolOrCourse', compact('GChartData',  'total'));
    }

    public function admissionStat(){
        $applicants = Applicant::select(\DB::raw('batches.name, count(*) as total'))
        ->join('batches', 'batches.id', '=', 'applicants.batch_id')
        ->join('camps', 'camps.id', '=', 'batches.camp_id')
        ->where('camps.id', $this->camp_id)
        ->where('is_admitted', 1)
        ->groupBy('batches.name')->get();
        $rows = $applicants->count();
        $array = $applicants->toArray();

        $i = 0 ;
        $total = 0 ;
        $GChartData = array('cols'=> array(
                        array('id'=>'name','label'=>'梯次','type'=>'string'),
                        array('id'=>'people','label'=>'人數','type'=>'number'),
                        array('id'=>'annotation','role'=>'annotation','type'=>'number')
                    ),
                    'rows' => array());
        for($i = 0; $i < $rows; $i ++) {
            $record = $array[$i];
            array_push($GChartData['rows'], array('c' => array(
                array('v' => $record['name'] == null ? '其它' : $record['name']),
                array('v' => intval($record['total'])),
                array('v' => intval($record['total']))
            )));
            $total = $total + $record['total'];
        }
        $GChartData = json_encode($GChartData);

        return view('backend.statistics.admission', compact('GChartData',  'total'));
    }

    public function checkinStat(){
        $applicants = Applicant::select(\DB::raw('check_in.check_in_date, count(*) as total'))
        ->join('batches', 'batches.id', '=', 'applicants.batch_id')
        ->join('camps', 'camps.id', '=', 'batches.camp_id')
        ->join('check_in', 'applicants.id', '=', 'check_in.applicant_id')
        ->where('camps.id', $this->camp_id)
        ->where('is_admitted', 1)
        ->groupBy('check_in_date')->get();
        $rows = $applicants->count();
        $array = $applicants->toArray();

        $i = 0 ;
        $total = 0 ;
        $GChartData = array('cols'=> array(
                        array('id'=>'check_in_date','label'=>'日期','type'=>'string'),
                        array('id'=>'people','label'=>'人數','type'=>'number'),
                        array('id'=>'annotation','role'=>'annotation','type'=>'number')
                    ),
                    'rows' => array());
        for($i = 0; $i < $rows; $i ++) {
            $record = $array[$i];
            array_push($GChartData['rows'], array('c' => array(
                array('v' => $record['check_in_date'] == null ? '其它' : $record['check_in_date']),
                array('v' => intval($record['total'])),
                array('v' => intval($record['total']))
            )));
            $total = $total + $record['total'];
        }
        $GChartData = json_encode($GChartData);
        $batches = \App\Models\Batch::where('camp_id', $this->camp_id)->get();
        foreach($batches as $batch){
            $batch_applicants = Applicant::select(\DB::raw('check_in.check_in_date, count(*) as total'))
            ->join('check_in', 'applicants.id', '=', 'check_in.applicant_id')
            ->where('batch_id', $batch->id)
            ->where('is_admitted', 1)
            ->groupBy('check_in_date')->get();
            $rows = $batch_applicants->count();
            $array = $batch_applicants->toArray();

            $i = 0 ;
            $batch->total = 0 ;
            $batch_GChartData = array('cols'=> array(
                            array('id'=>'check_in_date','label'=>'日期','type'=>'string'),
                            array('id'=>'people','label'=>'人數','type'=>'number'),
                            array('id'=>'annotation','role'=>'annotation','type'=>'number')
                        ),
                        'rows' => array());
            for($i = 0; $i < $rows; $i ++) {
                $record = $array[$i];
                array_push($batch_GChartData['rows'], array('c' => array(
                    array('v' => $record['check_in_date'] == null ? '其它' : $record['check_in_date']),
                    array('v' => intval($record['total'])),
                    array('v' => intval($record['total']))
                )));
                $batch->total = $batch->total + $record['total'];
            }
            $batch->GChartData = json_encode($batch_GChartData);
        }

        return view('backend.statistics.checkin', compact('GChartData',  'total', 'batches'));
    }

    public function educationStat()
    {
        return $this->customOptionStat('system', '就讀學程統計', 
        [
            '博士班',
            '碩士班',
            '大學',
            '四技',
            '二技',
            '二專',
            '五專'
        ]);
    }

    public function wayStat()
    {
        return $this->customOptionStat('way', '得知管道統計', 
        [
            'FB',
            'IG',
            'Line',
            '官網',
            '網路(其它)',
            '班宣(有同學到班上宣傳)',
            '同學',
            '親友師長',
            '活動海報',
            '系所公告',
            '其它'
        ]);
    }

    public function industryStat()
    {
        return $this->customOptionStat('industry', '產業別統計', 
        [
            '電子科技/資訊/軟體/半導體',
            '傳產製造', '金融/保險/貿易',
            '法律/會計/顧問',
            '政治/宗教/社福', 
            '建築/營造/不動產',
            '醫師/藥師/藥廠/醫療照護',
            '民生服務業',
            '廣告/傳播/出版',
            '教育', 
            '設計/藝術/文創',
            '非營利組織',
            '其它'
        ]);
    }
    public function jobPropertyStat()
    {
        return $this->customOptionStat('job_property', '職務類型統計', [
            '負責人/公司經營管理', 
            '人資',
            '行政/總務',
            '法務',
            '財會/金融',
            '行銷/企劃',
            '專案管理',
            '客服/門市',
            '業務/貿易',
            '資訊軟體/研發',
            '生產製造/品管/環衛',
            '物流/運輸',
            '建築/營建',
            '影視演藝/幕後製作',
            '藝術創作/視覺設計',
            '文字創作/傳媒工作',
            '醫療/保健服務',
            '學術/教育輔導',
            '軍警消/保全',
            '其它'
        ]);
    }

    /**
     * 萬能自訂選項統計 (通用於產業別、職務屬性、學歷等各種下拉選單)
     *
     * @param string $type            欄位與萬能表的 type 標籤 (例如: 'industry', 'job_property')
     * @param string $title1          網頁畫面的副標題 (例如: '產業別統計', '職務屬性統計')
     * @param array  $defaultFallback 寫死在後端的預設順序清單 (當資料庫完全沒客製化設定時啟用)
     */
    protected function customOptionStat($type, $title1, array $defaultFallback = [])
    {
        // 1. 撈出該欄位與區域的原始統計數據 (將欄位動態代入 $type)
        $applicants = Applicant::select(\DB::raw($this->camp_table.'.'.$type.' as option_key, applicants.region, count(*) as total'))
            ->join('batches', 'batches.id', '=', 'applicants.batch_id')
            ->join('camps', 'camps.id', '=', 'batches.camp_id')
            ->join($this->camp_table, $this->camp_table.'.applicant_id', '=', 'applicants.id')
            ->where('camps.id', $this->camp_id)
            ->whereNull('applicants.deleted_at')
            ->groupBy($this->camp_table.'.'.$type, 'applicants.region')
            ->get();

        // 2. 取得該營隊綁定且排好序的指定分區清單
        $specifiedRegions = $this->getSpecifiedRegions();

        // 3. 呼叫 Model 靜態方法：優先看資料庫客製化，沒有就吃傳入的預設值
        $optionsOrder = \App\Models\CampCustomOption::getProcessedOptions(
            $this->camp_id, 
            ($this->batch_id ?? null), // 統計看全營隊，梯次傳 null
            $type, 
            $defaultFallback
        );

        // 4. 初始化各分區的暫存容器
        $rawGroupData = ['all' => [], 'other' => []];
        foreach ($specifiedRegions as $rName) { 
            $rawGroupData[$rName] = []; 
        }

        $totals = ['all' => 0, 'other' => 0];
        foreach ($specifiedRegions as $rName) { 
            $totals[$rName] = 0; 
        }

        // 預先依據排好的順序填充容器（確保沒人報名的選項也會出現且值為 0）
        foreach (array_merge(['all', 'other'], $specifiedRegions) as $gKey) {
            foreach ($optionsOrder as $optLabel) {
                $rawGroupData[$gKey][$optLabel] = 0;
            }
            if (!in_array('其它', $optionsOrder)) {
                $rawGroupData[$gKey]['其它'] = 0;
            }
        }

        // 5. 開始分流與累加數據
        foreach ($applicants as $record) {
            $userChoice = $record->option_key ? trim($record->option_key) : '其它';
            $region     = $record->region ? trim($record->region) : '';
            $count      = intval($record->total);

            // 防錯：不在定義清單中的髒資料，一律歸入「其它」
            if (!in_array($userChoice, $optionsOrder)) {
                $userChoice = '其它';
            }

            // 全區與分區累加
            $rawGroupData['all'][$userChoice] += $count;
            $totals['all'] += $count;

            if (in_array($region, $specifiedRegions)) {
                $rawGroupData[$region][$userChoice] += $count;
                $totals[$region] += $count;
            } else {
                $rawGroupData['other'][$userChoice] += $count;
                $totals['other'] += $count;
            }
        }

        // 6. 轉化為標準的 Google Chart JSON 格式
        $chartCols = [
            ['id' => 'option_label', 'label' => '項目', 'type' => 'string'],
            ['id' => 'people', 'label' => '人數', 'type' => 'number'],
            ['id' => 'annotation', 'role' => 'annotation', 'type' => 'number']
        ];

        $displayConfig = ['all' => '全區'];
        foreach ($specifiedRegions as $rName) { 
            $displayConfig[$rName] = $rName; 
        }
        $displayConfig['other'] = '其它區域';

        $chartCollection = [];

        foreach ($displayConfig as $key => $labelText) {
            $dateArray = $rawGroupData[$key];
            
            $rows = [];
            foreach ($dateArray as $optName => $count) {
                $rows[] = ['c' => [
                    ['v' => $optName],
                    ['v' => $count],
                    ['v' => $count]
                ]];
            }

            $chartCollection[$key] = [
                'label' => $labelText,
                'total' => $totals[$key],
                'data'  => ['cols' => $chartCols, 'rows' => $rows]
            ];
        }

        $chartCollectionJson = json_encode($chartCollection);
        $specifiedKeys = $specifiedRegions;

        // 💡 這裡將副標題動態傳進 View 裡，連 Blade 都能共用！
        return view('backend.statistics.customOption', compact('chartCollectionJson', 'specifiedKeys', 'title1'));
    }
}
