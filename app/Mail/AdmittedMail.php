<?php

namespace App\Mail;

use App\Models\Applicant;
use App\Models\DynamicStat;
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
    public $attachment;
    public $etc;
    public $carers_unified;
    public $carers;
    public $content_link_chn;
    public $content_link_eng;

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
        $this->attachment = $attachment;
        $this->etc = $this->applicant->user?->roles?->where("camp_id", \App\Models\Vcamp::find($this->applicant->camp->id)->mainCamp->id)->first()?->section;
        $this->carers_unified = collect();
        $this->carers = collect();
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

        // 🎯 修正 1：加上 urltable_type 防錯，並使用 ?-> 避免 Null Pointer Exception
        // 🎯 修正 2：如果不確定是 $camp_info->camp_id 還是 $camp_info->id，建議統一為正解
        $campId = $camp_info->camp_id ?? $camp_info->id;

        $content_link_chn = DynamicStat::where('urltable_id', $campId)
            ->where('urltable_type', \App\Models\Camp::class) // 確保多型型別正確
            ->where('purpose', 'admittedMail_chn')
            ->value('google_sheet_url') ?? "#"; // 用 value() 直接拿欄位值，最安全且效能最好

        $content_link_eng = DynamicStat::where('urltable_id', $campId)
            ->where('urltable_type', \App\Models\Camp::class)
            ->where('purpose', 'admittedMail_eng')
            ->value('google_sheet_url') ?? "#";

        if ($camp_info->table == 'mcamp' || $camp_info->table == 'ecamp') {
            $vbatch = $this->applicant->batch->vbatch ?? null;

            if ($vbatch && $camp_info->table == 'mcamp') {
                $this->carers_unified = \App\Models\Applicant::where('batch_id', $vbatch->id)
                    ->whereHas('mvcamp', function ($query) {
                        $query->where('self_intro', \App\Models\Mvcamp::DESCRIPTION_UNIFIED_CONTACT);
                    })
                    ->get();
            }
            
            if ($vbatch) {
                $vbatch_id = $vbatch->id;
                $orgs = \App\Models\CampOrg::where('group_id', $this->applicant->group_id)
                    ->with([
                        'users.applicants' => function($query) use ($vbatch_id) {
                            $query->where('applicants.batch_id', $vbatch_id)
                                ->orderByDesc('applicants.id');
                        }
                    ])->get();

                $this->carers = $orgs
                    ->flatMap(fn($org) => $org->users)
                    ->flatMap(fn($user) => $user->applicants)
                    ->unique('id');
            }
        }

        $this->withSwiftMessage(function ($message) {
            $headers = $message->getHeaders();
            $headers->addTextHeader('time', time());
        });

        // 2026 special
        $mail_subject = ($camp_info->id == 130) ? '錄取通知<更正交通資訊>' : '錄取通知';

        $carers = $this->carers;
        $carers_unified = $this->carers_unified;

        $viewName = 'camps.' . $camp_info->table . ".admittedMail";
        $viewData = compact('applicant', 'camp_info', 'carers', 'carers_unified', 'content_link_chn', 'content_link_eng');

        if ($camp_info->table == 'ceocamp' || $camp_info->table == 'ecamp' || !$this->attachment) {
            return $this->subject($camp_info->abbreviation . $mail_subject)
                ->view($viewName, $viewData);
        }

        $fileName = '繳費暨錄取通知单' . \Carbon\Carbon::now()->format('YmdHis') . $camp_info->table . $this->applicant->group . $this->applicant->number . '.pdf';

        return $this->subject($camp_info->abbreviation . $mail_subject)
            ->view($viewName, $viewData)
            ->attachData($this->attachment, $fileName, [
                'mime' => 'application/pdf',
            ]);
    }
}
