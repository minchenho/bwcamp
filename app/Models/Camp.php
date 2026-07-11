<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Carbon\Carbon;

class Camp extends Model
{
    protected $table = 'camps';
    
    public $resourceNameInMandarin = '學員營隊資料';
    public $resourceDescriptionInMandarin = '每年學員營隊有關的資料，包括營隊名稱、簡稱、舉辦年、報名日期、錄取日期……等資料。';

    /**
     * 可批量賦值的欄位
     */
    protected $fillable = [
        'fullName', 'test', 'abbreviation', 'site_url', 'icon', 'table', 'year', 'variant', 'mode',
        'registration_start', 'registration_end', 'admission_announcing_date', 'admission_confirming_end',
        'rejection_showing_date', 'certificate_available_date', 'needed_to_reply_attend', 'final_registration_end',
        'payment_startdate', 'payment_deadline', 'fee', 'has_early_bird', 'early_bird_fee', 'early_bird_last_day',
        'discount_fee', 'discount_last_day', 'modifying_deadline', 'cancellation_deadline',
        'access_start', 'access_end'
    ];

    protected $guarded = [];

    /**
     * 屬性類型轉換
     */
    protected $casts = [
        'registration_start' => 'date:Y-m-d',
        'registration_end' => 'date:Y-m-d',
        'admission_announcing_date' => 'date:Y-m-d',
        'final_registration_end' => 'date:Y-m-d',
        'rejection_showing_date' => 'date:Y-m-d',
        'certificate_available_date' => 'date:Y-m-d',
        'admission_confirming_end' => 'date:Y-m-d',
        'modifying_deadline' => 'date:Y-m-d',
        'cancellation_deadline' => 'date:Y-m-d',
        'payment_startdate' => 'date:Y-m-d',
        'payment_deadline' => 'date:Y-m-d',
        'early_bird_last_day' => 'date:Y-m-d',
        'discount_last_day' => 'date:Y-m-d',
    ];

    /**
     * 追加追加至 JSON 序列化的虛擬屬性
     */
    protected $appends = [
        'registration_start_weekday',
        'registration_start_weekday_eng',
        'registration_start_weekday_short',
        'registration_end_weekday',
        'registration_end_weekday_eng',
        'registration_end_weekday_short',
        'admission_announcing_date_weekday',
        'admission_announcing_date_weekday_eng',
        'admission_announcing_date_weekday_short',
        'admission_confirming_end_weekday',
        'admission_confirming_end_weekday_eng',
        'admission_confirming_end_weekday_short',
        'cancellation_deadline_weekday',
        'payment_deadline_weekday',
        'early_bird_last_day_weekday',
        'discount_last_day_weekday',
        'batch_start_earliest',
        'batch_end_latest',
    ];

    /* -------------------------------------------------------------------------- */
    /* 核心標準關聯                                 */
    /* -------------------------------------------------------------------------- */

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function currencies(): BelongsToMany
    {
        return $this->belongsToMany(Currency::class, 'currency_camp_xref', 'camp_id', 'currency_id')
                    ->withPivot('is_std', 'is_fix_xrate', 'xrate_to_std');
    }

    public function regions(): BelongsToMany
    {
        $order = [
            "北苑", "基隆", "台北", "桃園", "新竹", "台中", "雲嘉", "雲林", "嘉義", "台南", "高屏", "高雄", "屏東", "宜蘭", "花蓮", "金馬",
            "北區", "桃區", "竹區", "中區", "南區", "高區",
            "北部", "中部", "南部", "東部",
            "海外", "其他"
        ];
        $placeholders = implode(',', array_fill(0, count($order), '?'));
        return $this->belongsToMany(Region::class, 'region_camp_xref', 'camp_id', 'region_id')
                    ->orderByRaw("FIELD(name, $placeholders)", $order);
    }

    public function applicants(): HasManyThrough
    {
        return $this->hasManyThrough(Applicant::class, Batch::class);
    }

    public function groups(): HasManyThrough
    {
        return $this->hasManyThrough(ApplicantsGroup::class, Batch::class);
    }

    /**
     * 🔄 反向關聯：由義工營隊(Vcamp) 反查它的學員營隊(Camp)」
     */
    public function mainCamp() //學員營隊
    {
        return $this->hasOneThrough(
            Camp::class, CampVcampXref::class, 
            'vcamp_id', 'id', 'id', 'camp_id'              
        )->withDefault();          
    }
    /**
     * 🔄 正向關聯：由學員營隊(Camp)」查 義工營隊(Vcamp)
     */
    public function vcamp()
    {
        return $this->hasOneThrough(
            Camp::class, CampVcampXref::class, 
            'camp_id', 'id', 'id', 'vcamp_id'    
        )->withDefault(); // ✨ 加上這行防禦大絕招！
    }

    public function dynamic_stats(): MorphMany
    {
        return $this->morphMany(DynamicStat::class, 'urltable');
    }

    /* -------------------------------------------------------------------------- */
    /* 全新現代化：樹狀組織關係鏈                            */
    /* -------------------------------------------------------------------------- */

    public function orgs(): HasMany
    {
        return $this->hasMany(CampOrg::class);
    }

    /**
     * 獲取該營隊的頂層根節點 (大會: depth = 0)
     */
    public function orgRoot()
    {
        return $this->orgs()->where('depth', 0)->first();
    }

    /**
     * 獲取第一層大組清單 (如：行政組、活動組、課務組: depth = 1)
     */
    public function orgDepth1(): HasMany
    {
        return $this->orgs()->where('depth', 1)->orderBy('order');
    }

    /**
     * 動態獲取指定深度 (Depth) 的所有組別/職務
     */
    public function orgsAtDepth($depth): HasMany
    {
        return $this->orgs()->where('depth', $depth)->orderBy('order');
    }

    /**
     * 獲取非大會以外的所有實體工作職務
     */
    public function roles(): HasMany
    {
        return $this->orgs()->where('depth', '>', 0);
    }

    /* -------------------------------------------------------------------------- */
    /* 歷史包袱安全防護區：保留舊拼法與舊 Layer/Section 方法名，內部重新導向新架構 */
    /* -------------------------------------------------------------------------- */
    
    public function batchs(): HasMany { return $this->batches(); }
    public function organizations(): HasMany { return $this->orgs(); }
    public function org_root() { return $this->orgRoot(); }
    public function org_layer1() { return $this->orgDepth1(); }
    public function org_layerx($prev_id) { return $this->orgs()->where('prev_id', $prev_id)->orderBy('order'); }
    public function layer1_sections() { return $this->orgDepth1(); }
    public function layer2_sections() 
    {
        $layer1_ids = $this->orgDepth1()->pluck('id');
        return $this->orgs()->whereIn('prev_id', $layer1_ids)->orderBy('order');
    }

    /* -------------------------------------------------------------------------- */
    /* 商業邏輯運算                                 */
    /* -------------------------------------------------------------------------- */

    // 🌟 加上 get...Attribute 變成標準 Accessor
    // 使用：$camp->isVcamp 或 $camp->is_vcamp 都可以
    public function getIsVcampAttribute(): bool
    {
        return str_contains($this->attributes['table'] ?? '', 'vcamp');
    }

    public function allSignAvailabilities(): HasManyThrough
    {
        return $this->hasManyThrough(BatchSignInAvailibility::class, Batch::class);
    }

    public static function getCampTable($batch_id)
    {
        return self::select('table as tableName')
            ->join('batches', 'batches.camp_id', '=', 'camps.id')
            ->where('batches.id', $batch_id)
            ->first()?->tableName;
    }

    /* -------------------------------------------------------------------------- */
    /* 現代化 Laravel Attribute 修改器                        */
    /* -------------------------------------------------------------------------- */

    /**
     * 決定當下的費用（原價或早鳥價）
     */
    protected function currentFee(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->early_bird_last_day) {
                    $earlyBirdLastDay = Carbon::createFromFormat('Y-m-d', $this->early_bird_last_day);
                    if ($this->has_early_bird && Carbon::today()->lte($earlyBirdLastDay)) {
                        return $this->early_bird_fee;
                    }
                }
                return $this->fee;
            }
        );
    }

    // 舊版呼叫相容
    public function getSetFeeAttribute() { return $this->current_fee; }

    /**
     * 決定當下的繳費期限（最終期限或早鳥期限）
     */
    protected function currentPaymentDeadline(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->has_early_bird && $this->early_bird_last_day) {
                    $earlyBirdLastDay = Carbon::createFromFormat('Y-m-d', $this->early_bird_last_day);
                    if (Carbon::today()->lte($earlyBirdLastDay) && ($this->table == 'tcamp' || $this->table == 'hcamp')) {
                        return $earlyBirdLastDay->subYears(1911)->format('ymd');
                    }
                }
                return $this->payment_deadline;
            }
        );
    }

    // 舊版呼叫相容
    public function getSetPaymentDeadlineAttribute() { return $this->current_payment_deadline; }

    protected function batchStartEarliest(): Attribute
    {
        return Attribute::make(get: fn () => $this->batches()->min('batch_start'));
    }

    protected function batchEndLatest(): Attribute
    {
        return Attribute::make(get: fn () => $this->batches()->max('batch_end'));
    }

    /* -------------------------------------------------------------------------- */
    /* 星期幾時間運算轉換區                              */
    /* -------------------------------------------------------------------------- */

    protected function registrationStartWeekday(): Attribute
    {
        return Attribute::make(get: fn () => $this->registration_start?->locale('zh_TW')->minDayName);
    }

    protected function registrationStartWeekdayEng(): Attribute
    {
        return Attribute::make(get: fn () => $this->registration_start?->format('l'));
    }

    protected function registrationStartWeekdayShort(): Attribute
    {
        return Attribute::make(get: fn () => $this->registration_start?->format('D'));
    }

    protected function registrationEndWeekday(): Attribute
    {
        return Attribute::make(get: fn () => $this->registration_end?->locale('zh_TW')->minDayName);
    }

    protected function registrationEndWeekdayEng(): Attribute
    {
        return Attribute::make(get: fn () => $this->registration_end?->format('l'));
    }

    protected function registrationEndWeekdayShort(): Attribute
    {
        return Attribute::make(get: fn () => $this->registration_end?->format('D'));
    }

    protected function admissionAnnouncingDateWeekday(): Attribute
    {
        return Attribute::make(get: fn () => $this->admission_announcing_date?->locale('zh_TW')->minDayName);
    }

    protected function admissionAnnouncingDateWeekdayEng(): Attribute
    {
        return Attribute::make(get: fn () => $this->admission_announcing_date?->format('l'));
    }

    protected function admissionAnnouncingDateWeekdayShort(): Attribute
    {
        return Attribute::make(get: fn () => $this->admission_announcing_date?->format('D'));
    }

    protected function admissionConfirmingEndWeekday(): Attribute
    {
        return Attribute::make(get: fn () => $this->admission_confirming_end?->locale('zh_TW')->minDayName);
    }

    protected function admissionConfirmingEndWeekdayEng(): Attribute
    {
        return Attribute::make(get: fn () => $this->admission_confirming_end?->format('l'));
    }

    protected function admissionConfirmingEndWeekdayShort(): Attribute
    {
        return Attribute::make(get: fn () => $this->admission_confirming_end?->format('D'));
    }

    protected function cancellationDeadlineWeekday(): Attribute
    {
        return Attribute::make(get: fn () => $this->cancellation_deadline?->locale('zh_TW')->minDayName);
    }

    protected function paymentDeadlineWeekday(): Attribute
    {
        return Attribute::make(get: fn () => $this->payment_deadline?->locale('zh_TW')->minDayName);
    }

    protected function earlyBirdLastDayWeekday(): Attribute
    {
        return Attribute::make(get: fn () => $this->early_bird_last_day?->locale('zh_TW')->minDayName);
    }

    protected function discountLastDayWeekday(): Attribute
    {
        return Attribute::make(get: fn () => $this->discount_last_day?->locale('zh_TW')->minDayName);
    }

    /**
     * 🧠 智慧自適應營隊屬性：$camp->resolved_camp
     * * 如果自己本身是學員營隊（!isVcamp），回傳自己
     */

    protected function resolvedCamp(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->isVcamp) { 
                    return $this->mainCamp;; 
                }
                return $this;
            }
        );
    }

    /**
     * 🧠 智慧自適應營隊屬性：$camp->resolved_vcamp
     * 如果自己本身就是虛擬營隊（isVcamp），回傳自己；
     */
    protected function resolvedVcamp(): Attribute
    {
        return Attribute::make(
            get: function () {
                // 🎯 1. 檢查自己是不是 vcamp
                if ($this->isVcamp) { 
                    return $this; // 😎 自己就是，直接把自己的 Model 完好無缺地吐回去！
                }

                // 🎯 2. 如果自己不是，才啟動 hasOneThrough 關聯去隔壁桌找
                return $this->vcamp; 
            }
        );
    }

}