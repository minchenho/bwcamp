<?php

namespace App\Mail;

use App\Models\Applicant;
use App\Models\Mvcamp;
use App\Models\Vcamp;
use App\Models\CampOrg;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\View;

class AdmittedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public $applicant;
    public $camp_info;
    public $campFullData;   //backward compatibility
    public $attachment;
    public $etc;
    public $carers_unified;
    public $carers;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($applicant, $camp_info, $attachment = null)
    {
        //
        $this->applicant = $applicant;
        $this->camp_info = $camp_info;
        $this->campFullData = $camp_info;
        $this->attachment = $attachment;
        $this->etc = $this->applicant->user?->roles?->where("camp_id", \App\Models\Vcamp::find($this->applicant->camp->id)->mainCamp->id)->first()?->section;
        $this->carers_unified = [];
        $this->carers = [];

        return;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $applicant = $this->applicant;
        $camp_info = $this->camp_info;
        $carers = [];

        //信中用到的外部連結
        $content_link_chn = $this->applicant->camp->dynamic_stats?->where('purpose', 'admittedMail_chn')?->first()?->google_sheet_url ?? [];
        $content_link_eng = $this->applicant->camp->dynamic_stats?->where('purpose', 'admittedMail_eng')?->first()?->google_sheet_url ?? [];

        if ($this->camp_info->table == 'mcamp' || $this->camp_info->table == 'ecamp' ) {
            $vbatch = $this->applicant->batch->vbatch ?? null;
            $vcamp = $this->applicant->camp->vcamp ?? null;

            if ($vbatch && $this->camp_info->table == 'mcamp') {
                $this->carers_unified =
                    \App\Models\Applicant::where('batch_id', $vbatch->id)
                    ->join('mvcamp', 'mvcamp.applicant_id', '=', 'applicants.id')
                    ->where('self_intro', \App\Models\Mvcamp::DESCRIPTION_UNIFIED_CONTACT)
                    ->get();
            }
            if ($vcamp) {
                $orgs = \App\Models\CampOrg::with('users.application_log')
                    ->where('group_id', $this->applicant->group_id)
                    ->get();
                $carers = $orgs->pluck("users")->flatten();
                $vcampBatchIds = $vcamp->batchs->pluck('id');

                $carers = $carers->map(function ($carer) use ($vcampBatchIds) {
                    $carer["mobile"] = $carer->application_log->whereIn('batch_id', $vcampBatchIds)->first()?->mobile ?? "";
                    return $carer;
                });
                $this->carers = $carers;
            }
        }

        $this->withSwiftMessage(function ($message) {
            $headers = $message->getHeaders();
            $headers->addTextHeader('time', time());
        });

        if ($this->camp_info->table == 'ceocamp' || $this->camp_info->table == 'ecamp' 
                || !$this->attachment) {
            // ceocamp/ecamp 不附加PDF，或attachment為空時不附加PDF
            return $this->subject($this->camp_info->abbreviation . '錄取通知')
                ->view('camps.' . $this->camp_info->table . ".admittedMail", compact('applicant', 'camp_info', 'carers', 'content_link_chn', 'content_link_eng'));
        } else {
            // 其他營隊附加PDF
            return $this->subject($this->camp_info->abbreviation . '錄取通知')
                ->view('camps.' . $this->camp_info->table . ".admittedMail", compact('applicant', 'camp_info', 'carers', 'content_link_chn', 'content_link_eng'))
                ->attachData($this->attachment, '繳費暨錄取通知單' . \Carbon\Carbon::now()->format('YmdHis') . $this->camp_info->table . $this->applicant->group . $this->applicant->number . '.pdf', [
                    'mime' => 'application/pdf',
                ]);
        }
    }
}
