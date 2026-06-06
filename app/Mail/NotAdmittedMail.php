<?php

namespace App\Mail;

use App\Models\Applicant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NotAdmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $applicant;
    public $batch;
    public $campInfo;
    public $mailType; //notAdmitted or thankYou

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($applicant, $batch, $campInfo, $mailType = 'notAdmitted') {
        //
        $this->applicant = $applicant;
        $this->batch = $batch;
        $this->campInfo = $campInfo;
        $this->mailType = $mailType;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build() {
        $this->withSwiftMessage(function ($message) {
            $headers = $message->getHeaders();
            $headers->addTextHeader('time', time());
        });

        $applicant = $this->applicant;
        $batch = $this->batch;
        $campInfo = $this->campInfo;
        $mailType = $this->mailType;

        if ($campInfo->table == 'ecamp' || $this->mailType == 'thankYou') {
            $mailSubject = $campInfo->abbreviation . '感謝函';
        } else {
            $mailSubject = $campInfo->abbreviation . '通知信';
        }

        return $this->subject($mailSubject)
            ->view('camps.' . $campInfo->table . ".notAdmittedMail", compact('applicant', 'batch', 'campInfo', 'mailType'));
    }
}
