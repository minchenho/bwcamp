<?php

namespace App\Listeners;

use App\Traits\EmailConfiguration;
use Illuminate\Queue\Events\JobProcessing;

/**
 * 佇列寄信前，依 mailable 的營隊切換寄件設定
 *
 * 取代原本覆寫 Illuminate\Queue\Jobs\Job::fire() 的做法（app/Overrides/Job.php）
 */
class SetQueuedMailConfiguration
{
    use EmailConfiguration;

    public function handle(JobProcessing $event): void
    {
        $command = $event->job->payload()['data']['command'] ?? null;
        // 加密的工作（ShouldBeEncrypted）不是序列化字串，略過
        if (!is_string($command) || !str_starts_with($command, 'O:')) {
            return;
        }

        $data = unserialize($command);
        if (isset($data->mailable->camp)) {
            self::setEmail($data->mailable->camp);
        }
    }
}
