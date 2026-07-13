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
        $this->carers_unified = collect();
        $this->carers = collect();

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

        // 信中用到的外部連結
        $content_link_chn = $this->applicant->camp->dynamic_stats?->where('purpose', 'admittedMail_chn')?->first()?->google_sheet_url ?? [];
        $content_link_eng = $this->applicant->camp->dynamic_stats?->where('purpose', 'admittedMail_eng')?->first()?->google_sheet_url ?? [];

        if ($this->camp_info->table == 'mcamp' || $this->camp_info->table == 'ecamp') {
            $vbatch = $this->applicant->batch->vbatch ?? null;

            if ($vbatch && $this->camp_info->table == 'mcamp') {
                $this->carers_unified = \App\Models\Applicant::where('batch_id', $vbatch->id)
                // 🎯 純過濾：只篩選出「對應的 mvcamp 裡 self_intro 符合條件」的 applicants
                ->whereHas('mvcamp', function ($query) {
                    $query->where('self_intro', \App\Models\Mvcamp::DESCRIPTION_UNIFIED_CONTACT);
                })
                ->get(); // 回傳的全部都是最純淨、ID 絕對不會被污染的 Applicant 模型集合
            }
            
            // ✨ 修正 1：正名變數為 $vbatch
            if ($vbatch) {
                $vbatch_id = $vbatch->id;
                $orgs = \App\Models\CampOrg::where('group_id', $this->applicant->group_id)
                    ->with([
                        'users.applicants' => function($query) use ($vbatch_id) {
                            $query->where('applicants.batch_id', $vbatch_id)
                                  ->orderByDesc('applicants.id'); // 🎯 安全指定前綴
                        }
                    ])->get();

                // 2. 核心大招：一路「壓平」到最深層，只把 applicants 抽出來
                $this->carers = $orgs
                    ->flatMap(function ($org) {
                        return $org->users;
                    })
                    ->flatMap(function ($user) {
                        return $user->applicants; 
                    })
                    ->unique('id'); // ✨ 修正 2：直接用 'id' 去重即可
            }
        }

        $this->withSwiftMessage(function ($message) {
            $headers = $message->getHeaders();
            $headers->addTextHeader('time', time());
        });

        // 2026 special
        if ($this->camp_info->id == 130) {
            $mail_subject = '錄取通知<更正交通資訊>';
        } else {
            $mail_subject = '錄取通知';
        }

        $carers = $this->carers;
        $carers_unified = $this->carers_unified;

        if ($this->camp_info->table == 'ceocamp' || $this->camp_info->table == 'ecamp' 
                || !$this->attachment) {
            // ceocamp/ecamp 不附加PDF，或attachment為空時不附加PDF
            return $this->subject($this->camp_info->abbreviation . $mail_subject)
                ->view('camps.' . $this->camp_info->table . ".admittedMail", compact('applicant', 'camp_info', 'carers', 'carers_unified', 'content_link_chn', 'content_link_eng'));
        } else {
            // 其他營隊附加PDF
            return $this->subject($this->camp_info->abbreviation . $mail_subject)
                ->view('camps.' . $this->camp_info->table . ".admittedMail", compact('applicant', 'camp_info', 'carers', 'carers_unified', 'content_link_chn', 'content_link_eng'))
                ->attachData($this->attachment, '繳費暨錄取通知单' . \Carbon\Carbon::now()->format('YmdHis') . $this->camp_info->table . $this->applicant->group . $this->applicant->number . '.pdf', [
                    'mime' => 'application/pdf',
                ]);
        }
    }
}
