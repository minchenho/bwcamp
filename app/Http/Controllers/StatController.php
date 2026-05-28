<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Traits\EmailConfiguration;

class StatController extends BackendController
{
    use EmailConfiguration;

    public function ageRangeStat(){
        //0-9,10-19 ...
        $applicants = Applicant::select(\DB::raw('CONCAT(FLOOR((YEAR(CURDATE()) - birthyear)/10)*10,"-",FLOOR((YEAR(CURDATE()) - birthyear)/10)*10+9) as agerange, count(*) as total'))
        ->join($this->camp_table, 'applicants.id', '=', $this->camp_table . '.applicant_id')
        ->join('batchs', 'batchs.id', '=', 'applicants.batch_id')
        ->join('camps', 'camps.id', '=', 'batchs.camp_id')
        ->where('camps.id', $this->camp_id)
        ->whereNull('applicants.deleted_at')
        ->groupBy('agerange')->orderBy('agerange')->get();

        // 這裡數最快，且不會觸發 appends 計算
        $rows = $applicants->count(); 
        // 之後再處理陣列
        $array = $applicants->each->setAppends([])->toArray();

        $i = 0 ;
        $total = 0 ;
        $GChartData = array('cols'=> array(
                        array('id'=>'agerange','label'=>'年齡級距','type'=>'string'),
                        array('id'=>'people','label'=>'人數','type'=>'number'),
                        array('id'=>'annotation','role'=>'annotation','type'=>'number')
                    ),
                    'rows' => array());
        for($i = 0; $i < $rows; $i ++) {
            $record = $array[$i];
            array_push($GChartData['rows'], array('c' => array(
                array('v' => ($record['agerange'] == null) ? '其他' : $record['agerange']),
                array('v' => intval($record['total'])),
                array('v' => intval($record['total']))
            )));
            $total = $total + $record['total'];
        }

        $GChartData = json_encode($GChartData);
        return view('backend.statistics.agerange', compact('GChartData',  'total'));
    }

    public function appliedDateStat() {
        $applicants = Applicant::select(\DB::raw('DATE_FORMAT(applicants.created_at, "%Y-%m-%d") as date, count(*) as total'))
        ->join($this->camp_table, 'applicants.id', '=', $this->camp_table . '.applicant_id')
        ->join('batchs', 'batchs.id', '=', 'applicants.batch_id')
        ->join('camps', 'camps.id', '=', 'batchs.camp_id')
        ->where('camps.id', $this->camp_id)
        ->whereNull('applicants.deleted_at')
        ->groupBy('date')->withTrashed()->get();
        $rows = $applicants->count();
        $array = $applicants->toArray();
        
        $i = 0 ;
        $total = 0 ;
        $GChartData = array(
            'cols'=> array(
                array('id'=>'date','label'=>'日期','type'=>'date'),
                array('id'=>'people','label'=>'人數','type'=>'number'),
                array('id'=>'annotation','role'=>'annotation','type'=>'number')
            ),
            'rows' => array()
        );
        $GChartData1 = array(
            'cols'=> array(
                array('id'=>'date','label'=>'日期','type'=>'date'),
                array('id'=>'people_accu','label'=>'累計人數','type'=>'number'),
                array('id'=>'annotation','role'=>'annotation','type'=>'number')
            ),
            'rows' => array()
        );

        for($i = 0; $i < $rows; $i ++) {
            $record = $array[$i];
            $year = (int) substr($record['date'], 0, 4);
            $month = ((int) substr($record['date'], 5, 2)) - 1;
            $day = (int) substr($record['date'], -2);
            array_push($GChartData['rows'], array('c' => array(
                array('v' => "Date($year, $month, $day)"),
                array('v' => intval($record['total'])),
                array('v' => intval($record['total']))
            )));
            $total = $total + $record['total'];
            array_push($GChartData1['rows'], array('c' => array(
                array('v' => "Date($year, $month, $day)"),
                array('v' => intval($total)),
                array('v' => intval($total))
            )));
        }
        $GChartData = json_encode($GChartData);
        $GChartData1 = json_encode($GChartData1);
        return view('backend.statistics.appliedDate', compact('GChartData','GChartData1', 'total'));
    }

    public function favoredEventStat(){
        $applicants = Applicant::select(\DB::raw('ecamp.favored_event as event, count(*) as total'))
        ->join('batchs', 'batchs.id', '=', 'applicants.batch_id')
        ->join('camps', 'camps.id', '=', 'batchs.camp_id')
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
        $applicants = Applicant::select(\DB::raw('applicants.gender, count(*) as total'))
        ->join($this->camp_table, 'applicants.id', '=', $this->camp_table . '.applicant_id')
        ->join('batchs', 'batchs.id', '=', 'applicants.batch_id')
        ->join('camps', 'camps.id', '=', 'batchs.camp_id')
        ->where('camps.id', $this->camp_id)
        ->whereNull('applicants.deleted_at')
        ->groupBy('applicants.gender')->get();
        $rows = $applicants->count();
        $array = $applicants->toArray();

        $total = 0 ;
        $GChartData = array('cols'=> array(
                        array('id'=>'gender_chn','label'=>'性別','type'=>'string'),
                        array('id'=>'people','label'=>'人數','type'=>'number'),
                        array('id'=>'annotation','role'=>'annotation','type'=>'number')
                    ),
                    'rows' => array());
        for($i = 0; $i < $rows; $i ++) {
            $record = $array[$i];
            array_push($GChartData['rows'], array('c' => array(
                array('v' => $record['gender_chn']),
                array('v' => intval($record['total'])),
                array('v' => intval($record['total']))
            )));
            $total = $total + $record['total'];
        }
        $GChartData = json_encode($GChartData);

        return view('backend.statistics.gender', compact('GChartData',  'total'));
    }

    public function countyStat() {
        // 1. 定義你想要的特定順序
        $customOrder = ['臺北市', '新北市', '基隆市', '桃園市', '新竹市', '新竹縣', '苗栗縣', '臺中市', '彰化縣', '南投縣', '雲林縣', '嘉義市', '嘉義縣', '臺南市', '高雄市', '屏東縣', '宜蘭縣', '花蓮縣', '臺東縣', '澎湖縣', '金門縣', '連江縣', '其他'];
        
        // 將陣列轉為 SQL FIELD 用的字串範例: "'台北市','新北市','基隆市'..."
        $orderString = "'" . implode("','", $customOrder) . "'";

        if ($this->camp_table == 'acamp' && $this->camp->year >= 2025) {
            $query = Applicant::select(\DB::raw('acamp.class_county as county, count(*) as total'));
        } else {
            $query = Applicant::select(\DB::raw('SUBSTRING(applicants.address, 1, 3) as county, count(*) as total'));
        }

        $applicants = $query->join($this->camp_table, 'applicants.id', '=', $this->camp_table . '.applicant_id')
            ->join('batchs', 'batchs.id', '=', 'applicants.batch_id')
            ->join('camps', 'camps.id', '=', 'batchs.camp_id')
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
            $label = $record->county ?: '其他';
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

    public function birthyearStat(){
        $applicants = Applicant::select(\DB::raw('CONCAT(birthyear, "(", YEAR(CURDATE()) - birthyear, "歲)") as birthyear_label, birthyear, count(*) as total'))
        ->join($this->camp_table, 'applicants.id', '=', $this->camp_table . '.applicant_id')
        ->join('batchs', 'batchs.id', '=', 'applicants.batch_id')
        ->join('camps', 'camps.id', '=', 'batchs.camp_id')
        ->where('camps.id', $this->camp_id)
        ->whereNull('applicants.deleted_at')
        ->groupBy('birthyear')
        ->orderBy('birthyear', 'asc') // 'asc' 是從西元 1980, 1981... 這樣排 (由老到年輕)
        ->get();

        // 這裡數最快，且不會觸發 appends 計算
        $rows = $applicants->count(); 
        // 之後再處理陣列
        $array = $applicants->each->setAppends([])->toArray();

        $i = 0 ;
        $total = 0 ;
        $GChartData = array('cols'=> array(
                        array('id'=>'birthyear','label'=>'年次(歲)','type'=>'string'),
                        array('id'=>'people','label'=>'人數','type'=>'number'),
                        array('id'=>'annotation','role'=>'annotation','type'=>'number')
                    ),
                    'rows' => array());
        for($i = 0; $i < $rows; $i ++) {
            $record = $array[$i];
            array_push($GChartData['rows'], array('c' => array(
                array('v' => $record['birthyear'] == null ? '其他' : $record['birthyear']),
                array('v' => intval($record['total'])),
                array('v' => intval($record['total']))
            )));
            $total = $total + $record['total'];
        }
        $GChartData = json_encode($GChartData);

        return view('backend.statistics.birthyear', compact('GChartData',  'total'));
    }

    public function batchesStat(){
        $applicants = Applicant::select(\DB::raw('batchs.name as batch, count(*) as total'))
        ->join('batchs', 'batchs.id', '=', 'applicants.batch_id')
        ->join('camps', 'camps.id', '=', 'batchs.camp_id')
        ->where('camps.id', $this->camp_id)
        ->whereNull('applicants.deleted_at')
        ->groupBy('batchs.name')->get();
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
                array('v' => $record['batch'] == null ? '其他' : $record['batch']),
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
        ->join('batchs', 'batchs.id', '=', 'applicants.batch_id')
        ->join('camps', 'camps.id', '=', 'batchs.camp_id')
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
        ->join('batchs', 'batchs.id', '=', 'applicants.batch_id')
        ->join('camps', 'camps.id', '=', 'batchs.camp_id')
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
                array('v' => $record['school_or_course'] == null ? '其他' : $record['school_or_course']),
                array('v' => intval($record['total'])),
                array('v' => intval($record['total']))
            )));
            $total = $total + $record['total'];
        }
        $GChartData = json_encode($GChartData);

        return view('backend.statistics.schoolOrCourse', compact('GChartData',  'total'));
    }

    public function admissionStat(){
        $applicants = Applicant::select(\DB::raw('batchs.name, count(*) as total'))
        ->join('batchs', 'batchs.id', '=', 'applicants.batch_id')
        ->join('camps', 'camps.id', '=', 'batchs.camp_id')
        ->where('camps.id', $this->camp_id)
        ->where('is_admitted', 1)
        ->groupBy('batchs.name')->get();
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
                array('v' => $record['name'] == null ? '其他' : $record['name']),
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
        ->join('batchs', 'batchs.id', '=', 'applicants.batch_id')
        ->join('camps', 'camps.id', '=', 'batchs.camp_id')
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
                array('v' => $record['check_in_date'] == null ? '其他' : $record['check_in_date']),
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
                    array('v' => $record['check_in_date'] == null ? '其他' : $record['check_in_date']),
                    array('v' => intval($record['total'])),
                    array('v' => intval($record['total']))
                )));
                $batch->total = $batch->total + $record['total'];
            }
            $batch->GChartData = json_encode($batch_GChartData);
        }

        return view('backend.statistics.checkin', compact('GChartData',  'total', 'batches'));
    }


    public function educationStat(){
        $str = 'education';
        if($this->camp_table == 'ycamp'){
            $str = 'system';
        }
        $applicants = Applicant::select(\DB::raw($this->camp_table . '.' . $str . ' as education, count(*) as total'))
        ->join('batchs', 'batchs.id', '=', 'applicants.batch_id')
        ->join('camps', 'camps.id', '=', 'batchs.camp_id')
        ->join($this->camp_table, $this->camp_table . '.applicant_id', '=', 'applicants.id')
        ->where('camps.id', $this->camp_id)
        ->whereNull('applicants.deleted_at')
        ->groupBy('education')->get();
        $rows = $applicants->count();
        $array = $applicants->toArray();

        $i = 0 ;
        $total = 0 ;
        $GChartData = array('cols'=> array(
                        array('id'=>'education','label'=>'學程','type'=>'string'),
                        array('id'=>'people','label'=>'人數','type'=>'number'),
                        array('id'=>'annotation','role'=>'annotation','type'=>'number')
                    ),
                    'rows' => array());
        for($i = 0; $i < $rows; $i ++) {
            $record = $array[$i];
            array_push($GChartData['rows'], array('c' => array(
                array('v' => $record['education'] == null ? '其他' : $record['education']),
                array('v' => intval($record['total'])),
                array('v' => intval($record['total']))
            )));
            $total = $total + $record['total'];
        }
        $GChartDataAll = json_encode($GChartData);

        if($this->camp_table == "hcamp"){
            $applicants = Applicant::select(\DB::raw($this->camp_table . '.education as education, count(*) as total'))
            ->join('batchs', 'batchs.id', '=', 'applicants.batch_id')
            ->join('camps', 'camps.id', '=', 'batchs.camp_id')
            ->join($this->camp_table, $this->camp_table . '.applicant_id', '=', 'applicants.id')
            ->where('camps.id', $this->camp_id)
            ->where('applicants.gender', 'M')
            ->groupBy($this->camp_table . '.education')->get();
            $rows = $applicants->count();
            $array = $applicants->toArray();

            $i = 0 ;
            $total = 0 ;
            $GChartData = array('cols'=> array(
                            array('id'=>'education','label'=>'學程','type'=>'string'),
                            array('id'=>'people','label'=>'人數','type'=>'number'),
                            array('id'=>'annotation','role'=>'annotation','type'=>'number')
                        ),
                        'rows' => array());
            for($i = 0; $i < $rows; $i ++) {
                $record = $array[$i];
                array_push($GChartData['rows'], array('c' => array(
                    array('v' => $record['education'] == null ? '其他' : $record['education']),
                    array('v' => intval($record['total'])),
                    array('v' => intval($record['total']))
                )));
                $total = $total + $record['total'];
            }
            $GChartDataM = json_encode($GChartData);

            $applicants = Applicant::select(\DB::raw($this->camp_table . '.education as education, count(*) as total'))
            ->join('batchs', 'batchs.id', '=', 'applicants.batch_id')
            ->join('camps', 'camps.id', '=', 'batchs.camp_id')
            ->join($this->camp_table, $this->camp_table . '.applicant_id', '=', 'applicants.id')
            ->where('camps.id', $this->camp_id)
            ->where('applicants.gender', 'M')
            ->groupBy($this->camp_table . '.education')->get();
            $rows = $applicants->count();
            $array = $applicants->toArray();

            $i = 0 ;
            $total = 0 ;
            $GChartData = array('cols'=> array(
                            array('id'=>'education','label'=>'學程','type'=>'string'),
                            array('id'=>'people','label'=>'人數','type'=>'number'),
                            array('id'=>'annotation','role'=>'annotation','type'=>'number')
                        ),
                        'rows' => array());
            for($i = 0; $i < $rows; $i ++) {
                $record = $array[$i];
                array_push($GChartData['rows'], array('c' => array(
                    array('v' => $record['education'] == null ? '其他' : $record['education']),
                    array('v' => intval($record['total'])),
                    array('v' => intval($record['total']))
                )));
                $total = $total + $record['total'];
            }
            $GChartDataF = json_encode($GChartData);
        } else {
            $GChartDataM = json_encode($GChartData);
            $GChartDataF = json_encode($GChartData);
        }

        return view('backend.statistics.education', compact('GChartDataAll', 'GChartDataM', 'GChartDataF', 'total'));
    }

    public function wayStat(){
        $applicants = Applicant::select(\DB::raw($this->camp_table.'.way as way, count(*) as total'))
        ->join('batchs', 'batchs.id', '=', 'applicants.batch_id')
        ->join('camps', 'camps.id', '=', 'batchs.camp_id')
        ->join($this->camp_table, $this->camp_table.'.applicant_id', '=', 'applicants.id')
        ->where('camps.id', $this->camp_id)
        ->whereNull('applicants.deleted_at')
        ->groupBy($this->camp_table.'.way')->get();
        $rows = $applicants->count();
        $array = $applicants->toArray();

        $i = 0 ;
        $total = 0 ;
        $GChartData = array('cols'=> array(
                        array('id'=>'way','label'=>'管道','type'=>'string'),
                        array('id'=>'people','label'=>'人數','type'=>'number'),
                        array('id'=>'annotation','role'=>'annotation','type'=>'number')
                    ),
                    'rows' => array());
        for($i = 0; $i < $rows; $i ++) {
            $record = $array[$i];
            array_push($GChartData['rows'], array('c' => array(
                array('v' => $record['way'] == null ? '其他' : $record['way']),
                array('v' => intval($record['total'])),
                array('v' => intval($record['total']))
            )));
            $total = $total + $record['total'];
        }
        $GChartData = json_encode($GChartData);

        return view('backend.statistics.way', compact('GChartData',  'total'));
    }

    public function industryStat(){
        $applicants = Applicant::select(\DB::raw($this->camp_table.'.industry as industry, count(*) as total'))
        ->join('batchs', 'batchs.id', '=', 'applicants.batch_id')
        ->join('camps', 'camps.id', '=', 'batchs.camp_id')
        ->join($this->camp_table, $this->camp_table.'.applicant_id', '=', 'applicants.id')
        ->where('camps.id', $this->camp_id)
        ->whereNull('applicants.deleted_at')
        ->groupBy($this->camp_table.'.industry')->get();

        $rows = $applicants->count();
        $array = $applicants->toArray();
        $i = 0 ;
        $total = 0 ;
        $GChartData = array('cols'=> array(
                        array('id'=>'industry','label'=>'產業別','type'=>'string'),
                        array('id'=>'people','label'=>'人數','type'=>'number'),
                        array('id'=>'annotation','role'=>'annotation','type'=>'number')
                    ),
                    'rows' => array());
        for($i = 0; $i < $rows; $i ++) {
            $record = $array[$i];
            array_push($GChartData['rows'], array('c' => array(
                array('v' => $record['industry'] == null ? '其他' : $record['industry']),
                array('v' => intval($record['total'])),
                array('v' => intval($record['total']))
            )));
            $total = $total + $record['total'];
        }
        $GChartData = json_encode($GChartData);

        return view('backend.statistics.industry', compact('GChartData',  'total'));
    }

    public function jobPropertyStat(){
        $applicants = Applicant::select(\DB::raw($this->camp_table.'.job_property as job_property, count(*) as total'))
        ->join('batchs', 'batchs.id', '=', 'applicants.batch_id')
        ->join('camps', 'camps.id', '=', 'batchs.camp_id')
        ->join($this->camp_table, $this->camp_table.'.applicant_id', '=', 'applicants.id')
        ->where('camps.id', $this->camp_id)
        ->groupBy($this->camp_table.'.job_property')->get();

        $rows = $applicants->count();
        $array = $applicants->toArray();

        $i = 0 ;
        $total = 0 ;
        $GChartData = array('cols'=> array(
                        array('id'=>'job_property','label'=>'工作屬性','type'=>'string'),
                        array('id'=>'people','label'=>'人數','type'=>'number'),
                        array('id'=>'annotation','role'=>'annotation','type'=>'number')
                    ),
                    'rows' => array());
        for($i = 0; $i < $rows; $i ++) {
            $record = $array[$i];
            array_push($GChartData['rows'], array('c' => array(
                array('v' => $record['job_property'] == null ? '其他' : $record['job_property']),
                array('v' => intval($record['total'])),
                array('v' => intval($record['total']))
            )));
            $total = $total + $record['total'];
        }
        $GChartData = json_encode($GChartData);

        return view('backend.statistics.jobProperty', compact('GChartData',  'total'));
    }
}
