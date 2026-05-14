<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\ApplicantService;
use App\Traits\EmailConfiguration;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\View;
use App\Models\Applicant;


class SendAdmittedMail implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use EmailConfiguration;

    protected $applicant;
    protected $applicantId;
    protected $camp_info;
    protected $tries = 400;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($applicantId, $camp_info)
    {
        $this->applicantId = $applicantId;
        $this->camp_info = $camp_info;

        //eager load lodging and traffic, which might be needed in the email view
        $relations = [$this->camp_info->table, 'lodging', 'traffic'];
        $this->applicant = Applicant::with($relations)->find($applicantId);

        View::share('applicant', $this->applicant);
        View::share('camp_info', $this->camp_info);
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

        if (!$this->applicant) {
            \Log::error("SendAdmittedMail: applicant {$this->applicantId} not found.");
            return;
        }

        $applicant = $this->applicant;
        $camp_info = $this->camp_info;

        $applicant = $applicantService->checkIfPaidEarlyBird($applicant);
        // MCH: 錄取通知信寄出時更新admitted_at，避免重複寄送錄取通知信
        $applicant->admitted_at = \Carbon\Carbon::now()->format('Y-m-d');    //MCH
        $applicant->save();
        $refundForm_url = $camp_info->dynamic_stats?->where('purpose', 'refundForm')?->first()?->google_sheet_url ?? "";


        // 動態載入電子郵件設定
        $this->setEmail($camp_info->table, $camp_info->variant);
        if (!isset($applicant->fee) || $applicant->fee == 0 || $camp_info->table == 'utcamp') {
            //無費用，或有費用但不需繳費單
            \Mail::to($applicant->email)->send(new \App\Mail\AdmittedMail($applicant, $camp_info));
        } else {
            //需繳費單
            $paymentFile = \PDF::loadView('camps.' . $camp_info->table . '.paymentFormPDF', compact('refundForm_url'))->setPaper('a3')->output();
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
