<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Cache; // 確保有引入 Cache
use App\Models\Camp;
use App\Models\Batch;
use Carbon\Carbon;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // 每日固定排程任務
        $this->scheduleAccountingChecks($schedule);
        $this->scheduleMaintenanceTasks($schedule);
        $this->scheduleCampExports($schedule);

        // 動態報到匯出排程
        $this->scheduleCheckInExports($schedule);
    }

    /**
     * 排程會計檢查任務
     */
    private function scheduleAccountingChecks(Schedule $schedule)
    {
        $schedule->command('check:Accounting ycamp')->dailyAt("16:30");
    }

    /**
     * 排程系統維護任務
     */
    private function scheduleMaintenanceTasks(Schedule $schedule)
    {
        $schedule->command('media-library:delete-old-temporary-uploads')->daily();
    }

    /**
     * 排程營隊資料匯出任務
     */
    private function scheduleCampExports(Schedule $schedule)
    {
        // CEO Camp 相關排程
        // $schedule->command('gen:BankSecondBarcode 96')->dailyAt("0:28");
        // $schedule->command('import:Form 96')->dailyAt("0:29");

        // 其他營隊匯出排程
        $schedule->command('export:Applicant 125')->dailyAt("0:10"); // ceovcamp_n
        $schedule->command('export:Applicant 129')->dailyAt("0:30"); // ceovcamp_s
        $schedule->command('export:Applicant 133')->dailyAt("0:50"); // ceovcamp_c
        $schedule->command('gen:BankSecondBarcode 124')->dailyAt("00:32");
        $schedule->command('import:Form 124')->dailyAt("0:33");
        $schedule->command('export:Applicant 124')->dailyAt("1:10"); // ceocamp_n
        $schedule->command('gen:BankSecondBarcode 128')->dailyAt("00:40");
        $schedule->command('import:Form 128')->dailyAt("0:41");
        $schedule->command('export:Applicant 128')->dailyAt("1:30"); // ceocamp_s
        $schedule->command('gen:BankSecondBarcode 132')->dailyAt("00:48");
        $schedule->command('import:Form 132')->dailyAt("0:49");
        $schedule->command('export:Applicant 132')->dailyAt("1:50"); // ceocamp_c
        $schedule->command('export:Applicant 120')->dailyAt("2:00"); // ecamp_n
        $schedule->command('export:Applicant 122')->dailyAt("2:30"); // ecamp_s
        $schedule->command('export:Applicant 130')->dailyAt("2:50"); // ecamp_c
        $schedule->command('export:Applicant 121')->dailyAt("3:00"); // evcamp_n
        $schedule->command('export:Applicant 123')->dailyAt("3:30"); // evcamp_s
        $schedule->command('export:Applicant 131')->dailyAt("3:50"); // evcamp_c
        $schedule->command('export:Applicant 126')->dailyAt("4:00"); // ycamp
        // $schedule->command('export:Applicant 108')->dailyAt("0:30");  // tcamp

        //utcamp
        //$schedule->command('gen:BankSecondBarcode 118')->dailyAt("11:49");
        //$schedule->command('export:Applicant 118')->dailyAt("11:50");  // utcamp
        //$schedule->command('gen:BankSecondBarcode 118')->dailyAt("16:29");
        //$schedule->command('export:Applicant 118')->dailyAt("16:31");  // utcamp
        //$schedule->command('gen:BankSecondBarcode 118')->dailyAt("0:49");
        //$schedule->command('export:Applicant 118')->dailyAt("00:50");  // utcamp
    }

    /**
     * 排程報到資料匯出任務
     * 規則：08:00-09:00 每分鐘執行; 09:00-12:00 每十分鐘執行
     * 終極優化版：把所有需要計算的排程參數（包含時間時段）通通打包存入快取
     */
    private function scheduleCheckInExports(Schedule $schedule)
    {
        $batch_ids = [240, 250, 251, 242, 247, 253];

        // 快取 key 叫 checkin_schedule_configs，保存 24 小時
        $scheduleConfigs = Cache::remember('checkin_schedule_configs', 86400, function () use ($batch_ids) {
            $batches = Batch::with('camp')->whereIn('id', $batch_ids)->get();
            $configs = [];

            foreach ($batches as $batch) {
                $str_day1 = $batch->batch_start->format('Y-m-d');
                
                // 在這裡直接呼叫你原本寫好的時間計算 Method
                //$timeRanges = $this->getCheckInTimeRanges($str_day1);
                $timeRanges = $this->getCheckInTimeRanges(Carbon::$today()->format('Y-m-d'));

                // 把這筆梯次要用的參數全部整理成一個純陣列
                $configs[] = [
                    'camp_id' => $batch->camp->id,
                    'time_config' => [
                        'everyMinute' => [
                            $timeRanges['test1']['peak'],
                            $timeRanges['day1']['peak'],
                            $timeRanges['day2']['peak'],
                        ],
                        'everyTenMinutes' => [
                            $timeRanges['test1']['normal'],
                            $timeRanges['day1']['normal'],
                            $timeRanges['day2']['normal'],
                        ]
                    ]
                ];
            }

            return $configs; // 這個乾淨的結構會被寫入快取
        });

        // 每分鐘執行排程時，只會跑這個輕量的迴圈，直接讀取算好的參數
        foreach ($scheduleConfigs as $config) {
            $this->scheduleCheckInForCamp($schedule, $config['camp_id'], $config['time_config']);
        }
    }

    /**
     * 取得報到時間範圍設定
     */
    private function getCheckInTimeRanges($str_day1): array
    {
        //$batch = Batch::find($batch_id);
        //$str_day1 = $batch->batch_start->format('Y-m-d');
        $str_prevDay = Carbon::parse($str_day1)->subDay()->format('Y-m-d');
        $str_nextDay = Carbon::parse($str_day1)->addDay()->format('Y-m-d');

        $peak_start = ' 07:00:01';
        $peak_end = ' 15:00:00';
        $normal_start = ' 15:00:01';
        $normal_end = ' 21:00:00';

        return [
            // 測試時段
            'test1' => [
                'peak'   => ['start' => $str_prevDay.$peak_start,   'end' => $str_prevDay.$peak_end],
                'normal' => ['start' => $str_prevDay.$normal_start, 'end' => $str_prevDay.$normal_end],
            ],

            // 第一天
            'day1' => [
                'peak'   => ['start' => $str_day1.$peak_start,   'end' => $str_day1.$peak_end],
                'normal' => ['start' => $str_day1.$normal_start, 'end' => $str_day1.$normal_end],
            ],

            // 第二天
            'day2' => [
                'peak'   => ['start' => $str_nextDay.$peak_start,   'end' => $str_nextDay.$peak_end],
                'normal' => ['start' => $str_nextDay.$normal_start, 'end' => $str_nextDay.$normal_end],
            ],
        ];
    }

    /**
     * 為特定營隊設定報到匯出排程
     */
    private function scheduleCheckInForCamp(Schedule $schedule, int $campId, array $timeConfig)
    {
        $command = "export:CheckIn checkIn {$campId} --renew=1";
        //$command = "export:CheckIn signIn {$campId} --renew=1";

        // 設定每分鐘執行的時段
        if (isset($timeConfig['everyMinute'])) {
            $schedule->command($command)
                ->everyMinute()
                ->when(function () use ($timeConfig) {
                    return $this->isInTimeRanges($timeConfig['everyMinute']);
                })
                ->name("checkin-export-min-camp-{$campId}"); // 👈 加上唯一名稱識別
        }

        // 設定每十分鐘執行的時段
        if (isset($timeConfig['everyTenMinutes'])) {
            $schedule->command($command)
                ->everyTenMinutes()
                ->when(function () use ($timeConfig) {
                    return $this->isInTimeRanges($timeConfig['everyTenMinutes']);
                })
                ->name("checkin-export-10min-camp-{$campId}"); // 👈 加上唯一名稱識別
        }
    }

    /**
     * 檢查當前時間是否在指定的時間範圍內
     */
    private function isInTimeRanges(array $ranges): bool
    {
        $now = Carbon::now();

        foreach ($ranges as $range) {
            $start = Carbon::parse($range['start']);
            $end = Carbon::parse($range['end']);

            if ($now->between($start, $end)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
