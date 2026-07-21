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
     * 規則：07:00-15:00 每分鐘執行; 15:00-21:00 每十分鐘執行 (涵蓋前一天、第一天、第二天)
     */
    private function scheduleCheckInExports(Schedule $schedule)
    {
        $batch_ids = [240, 250, 251, 242, 247, 253];

        // 1. 只快取 DB 查詢結果（極輕量，快取 24 小時）
        $batchConfigs = Cache::remember('checkin_batch_configs', 86400, function () use ($batch_ids) {
            return Batch::with('camp:id')
                ->whereIn('id', $batch_ids)
                ->get()
                ->map(function ($batch) {
                    return [
                        'camp_id' => $batch->camp->id,
                        'day1_date' => $batch->batch_start->format('Y-m-d'), // 動態取出實際開營日期
                    ];
                })
                ->toArray();
        });

        // 2. 註冊排程（每次 schedule:run 執行此迴圈都不會查 DB）
        foreach ($batchConfigs as $config) {
            $campId = $config['camp_id'];
            //$day1Date = $config['day1_date'];
            $day1Date = '2026-07-22';
            $command = "export:CheckIn checkIn {$campId} --renew=1";

            // 高峰時段：每分鐘執行 (07:00:01 ~ 15:00:00)
            $schedule->command($command)
                ->everyMinute()
                ->when(function () use ($day1Date) {
                    return $this->isNowInCheckInWindow($day1Date, '07:00:01', '15:00:00');
                })
                ->name("checkin-export-min-camp-{$campId}")
                ->withoutOverlapping(); // 建議加上防重覆執行

            // 一般時段：每 10 分鐘執行 (15:00:01 ~ 21:00:00)
            $schedule->command($command)
                ->everyTenMinutes()
                ->when(function () use ($day1Date) {
                    return $this->isNowInCheckInWindow($day1Date, '15:00:01', '21:00:00');
                })
                ->name("checkin-export-10min-camp-{$campId}")
                ->withoutOverlapping();
        }
    }

    /**
     * 動態檢查「當前時間」是否落在指定開營日（前一天、第一天、第二天）的特定時間區間內
     */
    private function isNowInCheckInWindow(string $day1Date, string $startTime, string $endTime): bool
    {
        $now = Carbon::now();
        $todayStr = $now->format('Y-m-d');

        // 算出該梯次允許的三個日期 (前一天, 第一天, 第二天)
        $day1 = Carbon::parse($day1Date);
        $validDates = [
            $day1->copy()->subDay()->format('Y-m-d'),
            $day1->format('Y-m-d'),
            $day1->copy()->addDay()->format('Y-m-d'),
        ];

        // 1. 如果「今天」不在這 3 天內，直接判定不執行，極速返回
        if (!in_array($todayStr, $validDates, true)) {
            return false;
        }

        // 2. 如果今天符合，比對當前時間是否在指定區間內
        $start = Carbon::parse("{$todayStr} {$startTime}");
        $end   = Carbon::parse("{$todayStr} {$endTime}");

        return $now->between($start, $end);
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
