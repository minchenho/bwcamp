<?php

namespace App\Jobs;

use App\Models\Applicant;
use App\Services\ApplicantService;
use App\Traits\EmailConfiguration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\View;


class SendAdmittedMail implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use EmailConfiguration;

    protected $applicantId;
    protected $applicant;
    protected $camp_info;
    protected $tries = 400;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($applicantId, $campInfo)
    {
        $this->applicantId = $applicantId;
        $this->camp_info = $campInfo;
        //eager load lodging and traffic, which might be needed in the email view
        $relations = ['batch', $this->camp_info->table, 'lodging', 'traffic'];
        $this->applicant = Applicant::with($relations)->find($applicantId);

        if (!$this->applicant) {
            \Log::error("SendAdmittedMail: applicant {$this->applicantId} not found.");
            return;
        }

        return;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(ApplicantService $applicantService)
    {
        //
        sleep(3);
        ini_set('memory_limit', -1);
        $camp_info = $this->camp_info;
        $applicant = $this->applicant;

        $applicant = $applicantService->checkIfPaidEarlyBird($applicant);
        // MCH: 錄取通知信寄出時更新admitted_at，避免重複寄送錄取通知信
        $applicant->admitted_at = \Carbon\Carbon::now();    //資料庫統一存datetime格式，顯示時再轉換成date或datetime格式
        $applicant->save();
        
        // paymentFormPDF 中用到的外部連結
        $refundForm_url = $camp_info->dynamic_stats?->where('purpose', 'refundForm')?->first()?->google_sheet_url ?? "";

        // 動態載入電子郵件設定
        $this->setEmail($camp_info->table, $camp_info->variant);
        if (!isset($applicant->fee) || $applicant->fee == 0 || $camp_info->table == 'utcamp' || $camp_info->table == 'ycamp') {
            //無費用，或有費用但不需繳費單
            \Mail::to($applicant->email)->send(new \App\Mail\AdmittedMail($applicant, $camp_info));
        } else {
            //需繳費單
            $paymentFile = \PDF::loadView('camps.' . $camp_info->table . '.paymentFormPDF', compact('applicant', 'camp_info', 'refundForm_url'))->setPaper('a3')->output();
            \Mail::to($applicant->email)->send(new \App\Mail\AdmittedMail($applicant, $camp_info, $paymentFile));
        }
        \logger('SendAdmittedMail: applicant ' . $this->applicantId . ' Email: ' . $applicant->email . ' success');
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware()
    {
        if (!$this->applicant) {
            \Sentry\captureException(new \Exception('SendAdmittedMail: Applicant not found'));
            return [];
        }
        return [new WithoutOverlapping($this->camp_info->id)];
    }

    /**
     * The unique ID of the job.
     *
     * @return string
     */
    public function uniqueId()
    {
        return $this->applicantId;
    }

    /**
     * Determine the time at which the job should timeout.
     *
     * @return \DateTime
     */
    public function retryUntil()
    {
        return now()->addMinutes(60);
    }
}
