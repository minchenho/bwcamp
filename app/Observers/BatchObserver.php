<?php

namespace App\Observers;

use App\Models\Batch;

class BatchObserver
{
    public $afterCommit = true;

    public function boot()
    {
        Batch::observe(BatchObserver::class);
    }

    /**
     * Handle the Batch "created" event.
     *
     * @param  \App\Models\Batch  $batch
     * @return void
     */
    public function created(Batch $batch)
    {
        // 組別改由「組別編製」頁建立，梯次存檔時不再依 num_groups 自動建組
    }

    /**
     * Handle the Batch "updated" event.
     *
     * @param  \App\Models\Batch  $batch
     * @return void
     */
    public function updated(Batch $batch)
    {
        // 同 created()
    }

    /**
     * Handle the Batch "deleted" event.
     *
     * @param  \App\Models\Batch  $batch
     * @return void
     */
    public function deleted(Batch $batch)
    {
        //
    }

    /**
     * Handle the Batch "restored" event.
     *
     * @param  \App\Models\Batch  $batch
     * @return void
     */
    public function restored(Batch $batch)
    {
        //
    }

    /**
     * Handle the Batch "force deleted" event.
     *
     * @param  \App\Models\Batch  $batch
     * @return void
     */
    public function forceDeleted(Batch $batch)
    {
        //
    }
}
