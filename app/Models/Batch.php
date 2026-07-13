<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Batch extends Model
{
    protected $table = 'batches';

    public $resourceNameInMandarin = '梯次';

    public $resourceDescriptionInMandarin = '營隊中的梯次。';

    protected $fillable = ['camp_id', 'name', 'admission_suffix', 'batch_start', 'batch_end',
    'is_appliable', 'is_late_registration_end', 'late_registration_end', 'locationName', 'location',
    'check_in_day', 'tel', 'num_groups', 'contact_card'];

    protected $casts = [
        'batch_start' => 'date:Y-m-d',
        'batch_end' => 'date:Y-m-d',
        'late_registration_end' => 'date:Y-m-d',
        'check_in_day' => 'date:Y-m-d',
    ];

    /*
        put attribute in $appends，這樣當把 Model 轉成 JSON 時，這些欄位才會出現
    */
    protected $appends = [
        'batch_start_weekday',      //default chinese
        'batch_start_weekday_eng',  //english
        'batch_start_weekday_short',    //english short
        'batch_end_weekday',    //default chinese
        'batch_end_weekday_eng',    //english
        'batch_end_weekday_short',  //english short
        
        // ✨ 新增智慧型虛擬屬性至 JSON 序列化清單
        'resolved_batch',
        'resolved_vbatch',
    ];

    /* -------------------------------------------------------------------------- */
    /* 核心標準關聯                                                               */
    /* -------------------------------------------------------------------------- */

    public function camp(): BelongsTo
    {
        return $this->belongsTo(Camp::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(ApplicantsGroup::class);
    }

    public function applicants(): HasMany
    {
        return $this->hasMany(Applicant::class);
    }

    public function sign_info($date = null)
    {
        $relation = $this->hasMany(BatchSignInAvailibility::class, 'batch_id')->orderBy('start', 'asc');
        if ($date) {
            $relation = $relation->where('start', 'like', "%{$date}%")->get();
        }
        return $relation;
    }

    public function canSignNow()
    {
        return $this->hasOne(BatchSignInAvailibility::class, 'batch_id')
                ->where([['start', '<=', now()], ['end', '>=', now()]])->first();
    }

    public function dynamic_stats(): MorphMany
    {
        return $this->morphMany(DynamicStat::class, 'urltable');
    }

    /**
     * 🔄 正向關聯：由學員梯次(Batch)」查 義工梯次(Vbatch)
     */
    public function vbatch(): HasOneThrough
    {
        // batch's vbatch
        return $this->hasOneThrough(Vbatch::class, BatchVbatchXref::class, 'batch_id', 'id', 'id', 'vbatch_id');
    }

    /**
     * 🔄 反向關聯：由義工梯次(Vbatch) 反查它的學員梯次(Batch)
     */
    public function mainBatch(): HasOneThrough
    {
        // vbatch's main batch (學員梯次)
        // 基於單一表繼承(STI)，Vbatch 本質也是 Batch 表，故這裡指向 Batch::class 最安全穩固
        return $this->hasOneThrough(Batch::class, BatchVbatchXref::class, 'vbatch_id', 'id', 'id', 'batch_id');
    }
    
    // 使用 $batch->isVbatch 或 $batch->is_vbatch 都可以
    public function getIsVbatchAttribute(): bool
    {
        return (bool) ($this->camp?->isVcamp ?? false);
    }

    /* -------------------------------------------------------------------------- */
    /* 智慧自適應修改器 (Modern Laravel Attribute)                              */
    /* -------------------------------------------------------------------------- */

    /**
     * 🧠 智慧自適應梯次屬性：$batch->resolved_batch
     * 如果自己本身是學員梯次（!isVbatch），回傳自己；如果自己是義工梯次，回傳對應的學員主梯次。
     */
    protected function resolvedBatch(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->isVbatch) { 
                    return $this->mainBatch;
                }
                return $this;
            }
        );
    }

    /**
     * 🧠 智慧自適應梯次屬性：$batch->resolved_vbatch
     * 如果自己本身就是義工梯次（isVbatch），回傳自己；如果是學員梯次，去查關聯的義工梯次。
     */
    protected function resolvedVbatch(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->isVbatch) { 
                    return $this;
                }
                return $this->vbatch; 
            }
        );
    }

    /* -------------------------------------------------------------------------- */
    /* 星期幾時間運算轉換區                                                       */
    /* -------------------------------------------------------------------------- */

    /*
     * 取得 batch_start 日期的星期幾
     */
    protected function batchStartWeekday(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->batch_start?->locale('zh_TW')->minDayName, // 一
        );
    }

    protected function batchStartWeekdayEng(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->batch_start?->format('l'), // Monday
        );
    }

    protected function batchStartWeekdayShort(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->batch_start?->format('D'), // Mon
        );
    }

    /*
     * 取得 batch_end 日期的星期幾
     */
    protected function batchEndWeekday(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->batch_end?->locale('zh_TW')->minDayName, // 一
        );
    }

    protected function batchEndWeekdayEng(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->batch_end?->format('l'), // Monday
        );
    }

    protected function batchEndWeekdayShort(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->batch_end?->format('D'), // Mon
        );
    }
}