<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Enums\Gender;
use App\Enums\AttendanceStatus;
use App\Services\PhoneFormatter;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class Applicant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'batch_id', 'camp_id', 'name', 'english_name', 'region', 'region_id', 'avatar','gender',
        'group_id', 'number_id', 'is_admitted', 'admitted_at', 'is_paid', 'is_attend',
        'birthyear', 'birthmonth', 'birthday', 'birthdate',
        'age_range', 'nationality', 'idno',
        'is_foreigner', 'is_allow_notified', 'mobile', 'phone_home', 'phone_work',
        'fax', 'line', 'wechat', 'email', 'zipcode', 'address',
        'emergency_name', 'emergency_relationship', 'emergency_mobile', 'emergency_phone_home', 'emergency_phone_work', 'emergency_fax',
        'introducer_name', 'introducer_relationship', 'introducer_phone', 'introducer_email', 'introducer_participated',
        'portrait_agree', 'profile_agree', 'expectation','fee', 'tax_id_no', 'created_at'
    ];

    protected $casts = [
        'admitted_at' => 'datetime',    //自訂的timestamp欄位必須主動宣告
    ];

    protected $appends = [
        //先轉幾個會用到的，其它之後再加
        'age',
        'birthdate_display',
        'birthdate_valid',
        'camp_table',
        'mobile_display',
        'phone_home_display',
        'phone_work_display',
        'emergency_mobile_display',
        'emergency_phone_home_display',
        'emergency_phone_work_display',
        'introducer_phone_display',
        'mobile_dial',
        'phone_home_dial',
        'phone_work_dial',
        'emergency_mobile_dial',
        'emergency_phone_home_dial',
        'emergency_phone_work_dial',
        'introducer_phone_dial',
        'portrait_agree_display',
        'profile_agree_display',
        'gender_chn',
        'is_attend_chn',
        ];

    public $resourceNameInMandarin = '一般學員資料';
    public $resourceDescriptionInMandarin = '學員報名表或詳細資料頁面中的資料。';

    private static $campCache;

    public function user()
    {
        $localKey = ($this->applicant_id ?? false) ? 'applicant_id' : 'id';
        return $this->hasOneThrough(User::class, UserApplicantXref::class, 'applicant_id', 'id', $localKey, 'user_id');
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class, 'batch_id', 'id');
    }

    public function camp()
    {
        // 🚀 核心優化：直接走 camp_id 欄位，刪除原本痛苦的 hasOneThrough
        return $this->belongsTo(Camp::class, 'camp_id');
    }

    public function vcamp()
    {
        // 同理，如果虛擬營隊也是跟 camp_id 綁定，直接改為 belongsTo
        return $this->belongsTo(Vcamp::class, 'camp_id');
    }

    /**
     * 作用域：只撈取未取消報名的有效學員
     * 用法：Applicant::active()
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * 作用域：快速撈取特定營隊的有效學員 (完美觸發 idx_camp_active_applicants 複合索引)
     * 用法：Applicant::ofCamp($campId)->get();
     */
    public function scopeOfCamp($query, $campId)
    {
        return $query->where('camp_id', $campId)->active();
    }

    /**
     * 作用域：快速撈取特定梯次的有效學員 (完美觸發 applicants_batch_id_deleted_at_index 複合索引)
     * 用法：Applicant::ofBatch($batchId)->get();
     */
    public function scopeOfBatch($query, $batchId)
    {
        return $query->where('batch_id', $batchId)->active();
    }

    /**
     * Model 的啟動與事件監聽
     */
    protected static function booted()
    {
        static::creating(function ($applicant) {
            // 防呆機制：如果存檔時有填 batch_id，但是沒填 camp_id
            if ($applicant->batch_id && !$applicant->camp_id) {
                // 自動透過梯次關聯，把正確的 camp_id 撈出來補上，確保反正規化資料百分之百完美同步！
                $batch = Batch::find($applicant->batch_id);
                if ($batch) {
                    $applicant->camp_id = $batch->camp_id;
                }
            }
        });
    }

    public function checkInData()
    {
        return $this->hasMany(CheckIn::class);
    }

    public function traffic()
    {
        return $this->hasOne(Traffic::class, 'applicant_id', 'id');
    }

    public function lodging()
    {
        return $this->hasOne(Lodging::class, 'applicant_id', 'id');
    }

    public function acamp()
    {
        return $this->hasOne(Acamp::class, 'applicant_id', 'id');
    }
    public function avcamp()
    {
        return $this->hasOne(Avcamp::class, 'applicant_id', 'id');
    }
    public function actcamp()
    {
        return $this->hasOne(Actcamp::class, 'applicant_id', 'id');
    }
    public function actvcamp()
    {
        return $this->hasOne(Actvcamp::class, 'applicant_id', 'id');
    }
    public function ceocamp()
    {
        return $this->hasOne(Ceocamp::class, 'applicant_id', 'id');
    }
    public function ceovcamp()
    {
        return $this->hasOne(Ceovcamp::class, 'applicant_id', 'id');
    }
    public function ecamp()
    {
        return $this->hasOne(Ecamp::class, 'applicant_id', 'id');
    }
    public function evcamp()
    {
        return $this->hasOne(Evcamp::class, 'applicant_id', 'id');
    }
    public function hcamp()
    {
        return $this->hasOne(Hcamp::class, 'applicant_id', 'id');
    }
    public function icamp()
    {
        return $this->hasOne(Icamp::class, 'applicant_id', 'id');
    }
    public function ivcamp()
    {
        return $this->hasOne(Ivcamp::class, 'applicant_id', 'id');
    }
    public function lrcamp()
    {
        return $this->hasOne(Lrcamp::class, 'applicant_id', 'id');
    }
    public function lrvcamp()
    {
        return $this->hasOne(Lrvcamp::class, 'applicant_id', 'id');
    }
    public function mcamp()
    {
        return $this->hasOne(Mcamp::class, 'applicant_id', 'id');
    }
    public function mvcamp()
    {
        return $this->hasOne(Mvcamp::class, 'applicant_id', 'id');
    }
    public function nycamp()
    {
        return $this->hasOne(Nycamp::class, 'applicant_id', 'id');
    }
    public function nyvcamp()
    {
        return $this->hasOne(Nyvcamp::class, 'applicant_id', 'id');
    }
    public function scamp()
    {
        return $this->hasOne(Scamp::class, 'applicant_id', 'id');
    }
    public function svcamp()
    {
        return $this->hasOne(Svcamp::class, 'applicant_id', 'id');
    }
    public function tcamp()
    {
        return $this->hasOne(Tcamp::class, 'applicant_id', 'id');
    }
    public function tvcamp()
    {
        return $this->hasOne(Tvcamp::class, 'applicant_id', 'id');
    }
    public function utcamp()
    {
        return $this->hasOne(Utcamp::class, 'applicant_id', 'id');
    }
    public function utvcamp()
    {
        return $this->hasOne(Utvcamp::class, 'applicant_id', 'id');
    }
    public function wcamp()
    {
        return $this->hasOne(Wcamp::class, 'applicant_id', 'id');
    }
    public function wvcamp()
    {
        return $this->hasOne(Wvcamp::class, 'applicant_id', 'id');
    }
    public function ycamp()
    {
        return $this->hasOne(Ycamp::class, 'applicant_id', 'id');
    }
    public function yvcamp()
    {
        return $this->hasOne(Yvcamp::class, 'applicant_id', 'id');
    }

    public function signData($orderBy = "desc")
    {
        return $this->hasMany(SignInSignOut::class)->orderBy('id', $orderBy);
    }

    public function sign_in_info()
    {
        return $this->hasMany(SignInSignOut::class)->whereType('in');
    }
    public function sign_out_info()
    {
        return $this->hasMany(SignInSignOut::class)->whereType('out');
    }

    public function contactlogs() 
    { 
	return $this->hasMany(ContactLog::class); 
    }
    public function contactlog() 
    {
    	return $this->contactlogs(); 
    }

    public function hasSignedThisTime($datetime)
    {
        return $this->signData()->whereHas('referencedAvailability', function ($q) use ($datetime) {
            $q->where([['start', '<=', $datetime], ['end', '>=', $datetime]]);
        })->first();
    }

    public function hasAlreadySigned($availability_id)
    {
        return $this->signData()->whereAvailabilityId($availability_id)->first();
    }

    //學員的小組和座號
    public function groupRelation()
    {
        return $this->belongsTo(ApplicantsGroup::class, 'group_id', 'id');
    }
    public function numberRelation()
    {
        return $this->belongsTo(GroupNumber::class, 'number_id', 'id');
    }
    
    //取得和學員小組相關的職務，通常是小組長，副小組長，關懷員
    public function groupOrgRelation()
    {
        return $this->belongsTo(CampOrg::class, 'group_id', 'id');
    }

    //取得關懷員
    public function carers()
    {
        return $this->belongsToMany(\App\Models\User::class, 'carer_applicant_xrefs', 'applicant_id', 'user_id');
    }

    //直接取得關懷員的名字
    public function carer_names()
    {
        //to concatenate the names of all carers
        //return $this->carers()->implode('name', ', ');
        return $this->carers->flatten()->pluck('name')->implode(',');
    }

    public function dynamic_stats(): MorphMany
    {
        return $this->morphMany(DynamicStat::class, 'urltable');
    }

    protected function gender(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                return match ($value) {
                    '男' => 'M', 
		    '女' => 'F',
                    '非常規性別' => 'NC', 
		    '不提供' => 'NS',
                    'M', 'F', 'NC', 'NS' => $value,
                    default => null,
                };
            },
            get: fn ($value) => $value
        );
    }

    protected function genderChn(): Attribute
    {
        return Attribute::get(fn () => match ($this->gender) {
            'M' => '男', 
	    'F' => '女',
            'NC' => '非常規性別', 
	    'NS' => '不提供',
            default => '-',
        });
    }


    protected function isAttend(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                // 如果傳進來的值本來就是數字 (0-5)，就直接存入
                if (is_numeric($value) && $value >= 0 && $value <= 5) {
                    return (int) $value;
                }

                // 如果傳進來的是中文，則進行轉換
                return match ($value) {
                    '不參加'   => 0,
                    '參加'     => 1,
                    '尚未決定' => 2,
                    '聯絡不上' => 3,
                    '無法全程' => 4,
                    '尚未聯絡' => 5,
                    default    => null,
                };
            },
            get: fn ($value) => $value
        );
    }

    protected function isAttendChn(): Attribute
    {
        return Attribute::get(fn () => match ($this->is_attend) {
            0 => '不參加', 
	    1 => '參加', 
	    2 => '尚未決定',
            3 => '聯絡不上', 
	    4 => '無法全程', 
	    5 => '尚未聯絡',
            default => '尚未聯絡',
        });
    }

    public function contactlogHTML($isShowVolunteers = false, $applicant, $camp = null)
    {
        if (!self::$campCache) {
            self::$campCache = $applicant->camp;
        }
        $firstNote = $applicant->contactlog?->sortByDesc('id')->first()?->notes;
        $str = \Str::limit($firstNote ?? "-", 50, '...') ?? "-";
        $str .= "<div>";
        $str .= '<a href="' . route("showAttendeeInfoGET", self::$campCache->id) . '?snORadmittedSN=' . $applicant->id . '&openExternalBrowser=1#new" target="_blank" class="text-primary">⊕新增關懷記錄</a>';
        if (count($applicant->contactlog)) {
            $str .= "&nbsp;&nbsp;";
            $str .= '<a href="' . route("showContactLogs", [self::$campCache->id, $applicant->id]) . '" target="_blank">🔍看更多</a>';
        }
        $str .= "</div>";
        return $str;
    }

    public function contactlogHTMLoptimized($isShowVolunteers = false, $camp = null)
    {
        if (!self::$campCache) {
            self::$campCache = $this->camp;
        }
        $firstNote = $this->contactlog?->sortByDesc('id')->first()?->notes;
        $str = \Str::limit($firstNote ?? "-", 50, '...') ?? "-";
        $str .= "<div>";
        $str .= '<a href="' . route("showAttendeeInfoGET", self::$campCache->id) . '?snORadmittedSN=' . $this->id . '&openExternalBrowser=1#new" target="_blank" class="text-primary">⊕新增關懷記錄</a>';
        if (count($this->contactlog)) {
            $str .= "&nbsp;&nbsp;";
            $str .= '<a href="' . route("showContactLogs", [self::$campCache->id, $this->id]) . '" target="_blank">🔍看更多</a>';
        }
        $str .= "</div>";
        return $str;
    }


    /* 換個方式處理birthdate, 分成顯示用display及計算用valid */
    /**
     * 建立一個虛擬的 birthdate 屬性
     */
    protected function birthdate(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                if (empty($value)) {
                    return [
		    	'birthyear' => null, 
		    	'birthmonth' => null, 
		    	'birthday' => null
		    ];
                }
                $date = Carbon::parse($value);
                return [
			'birthyear' => $date->year, 
			'birthmonth' => $date->month, 
			'birthday' => $date->day
		];
            },
            get: fn () => sprintf(
                '%04d-%02d-%02d',
                $this->birthyear ?: 0,
                $this->birthmonth ?: 0,
                $this->birthday ?: 0
            )
        );
    }

    /**
     * 1. 顯示專用：會出現 1990-00-00 (Readable Date)
     * 用法：$applicant->birthdate_display
     */
    protected function birthdateDisplay(): Attribute
    {
        return Attribute::get(function () {
            // 感覺沒有year, 還是可以顯示
            //if (!$this->birthyear) return '0000-00-00';

            return sprintf(
                '%04d-%02d-%02d',
                $this->birthyear ?: 0,
                $this->birthmonth ?: 0,
                $this->birthday ?: 0
            );
        });
    }

    /**
     * 2. 計算專用：自動補齊成合法日期 (Valid Date)
     * 用法：$applicant->birthdate_valid
     */
    protected function birthdateValid(): Attribute
    {
        return Attribute::get(function () {
            // 計算時好像都會用到year，如果沒有year，還是return null好了
            if (!$this->birthyear) {
                return null;
            }

            // 沒月補1月，沒日補1日，確保 Carbon 可以解析
            return Carbon::create(
                $this->birthyear,
                $this->birthmonth ?: 1,
                $this->birthday ?: 1
            );
        });
    }

    /*下面重寫
    public function getAgeAttribute()
    {
        if (is_string($this->birthdate)) {
            return Carbon::parse($this->birthdate)->diff(now())->format('%y');
        }
        return $this->birthdate?->diff(now())->format('%y');
    }*/

    /**
     * 自動根據出生年月日計算目前的年齡
     * 用法：$applicant->age
     */
    protected function age(): Attribute
    {
        return Attribute::get(function () {
            // 呼叫剛才寫好的 birthdate_valid (已自動補齊 1月1日)
            $date = $this->birthdate_valid;

            if (!$date) {
                return null; // 連年份都沒有，就無法算年齡
            }

            // 使用 Carbon 內建的 diffInYears 方法計算到今天為止的差距
            // Carbon::diffInYears() 會自動處理今天是否過了生日的問題
            return $date->diffInYears(now());
        });
    }

    /**
     * Get applicant's group by app version.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function group(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->groupRelation()->first()?->alias,
            set: fn ($value) => $value,
        );
    }

    /**
     * Get applicant's number by app version.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function number(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->numberRelation()->first()?->number,
            set: fn ($value) => $value,
        );
    }

    /**
     * 取得當前營隊關聯
     */
    protected function campTable(): Attribute
    {
        return Attribute::get(function () {
            // 透過 hasOneThrough 抓到的 camp
            // 加上 ?-> 避免 batch 或 camp 不存在時報錯
            return $this->camp?->table;
        });
    }

    /**
     * 重用格式化邏輯的 Accessors
     */
    protected function mobileDisplay(): Attribute
    {
        return Attribute::get(fn () => PhoneFormatter::format($this->mobile));
    }
    protected function phoneHomeDisplay(): Attribute
    {
        return Attribute::get(fn () => PhoneFormatter::format($this->phone_home));
    }
    protected function phoneWorkDisplay(): Attribute
    {
        return Attribute::get(fn () => PhoneFormatter::format($this->phone_work));
    }
    protected function emergencyMobileDisplay(): Attribute
    {
        return Attribute::get(fn () => PhoneFormatter::format($this->emergency_mobile));
    }
    protected function emergencyPhoneHomeDisplay(): Attribute
    {
        return Attribute::get(fn () => PhoneFormatter::format($this->emergency_phone_home));
    }
    protected function emergencyPhoneWorkDisplay(): Attribute
    {
        return Attribute::get(fn () => PhoneFormatter::format($this->emergency_phone_work));
    }
    protected function introducerPhoneDisplay(): Attribute
    {
        return Attribute::get(fn () => PhoneFormatter::format($this->introducer_phone));
    }
    protected function mobileDial(): Attribute
    {
        return Attribute::get(fn () => PhoneFormatter::dial($this->mobile));
    }
    protected function phoneHomeDial(): Attribute
    {
        return Attribute::get(fn () => PhoneFormatter::dial($this->phone_home));
    }
    protected function phoneWorkDial(): Attribute
    {
        return Attribute::get(fn () => PhoneFormatter::dial($this->phone_work));
    }
    protected function emergencyMobileDial(): Attribute
    {
        return Attribute::get(fn () => PhoneFormatter::dial($this->emergency_mobile));
    }
    protected function emergencyPhoneHomeDial(): Attribute
    {
        return Attribute::get(fn () => PhoneFormatter::dial($this->emergency_phone_home));
    }
    protected function emergencyPhoneWorkDial(): Attribute
    {
        return Attribute::get(fn () => PhoneFormatter::dial($this->emergency_phone_work));
    }
    protected function introducerPhoneDial(): Attribute
    {
        return Attribute::get(fn () => PhoneFormatter::dial($this->introducer_phone));
    }

    /*boolean*/
    protected function portraitAgreeDisplay(): Attribute
    {
        return Attribute::get(fn () => $this->portrait_agree ? '同意' : '不同意');
    }
    protected function profileAgreeDisplay(): Attribute
    {
        return Attribute::get(fn () => $this->profile_agree ? '同意' : '不同意');
    }

    /**
     * 終極前置過濾篩選器：只撈出該義工有權限管理的學員
     *
     * @param Builder $query
     * @param User $user 登入的義工
     * @param mixed $camp 當前營隊物件
     * @return Builder
     */
    public function scopeAccessibleBy(Builder $query, User $user, $camp): Builder
    {        
        // 1. 最高管理員特赦
        if ($user->isSuperuser) { 
            return $query->where('applicants.camp_id', $camp->id); 
        }

        // 2. 凡人義工檢查：走正門呼叫 getCampPermissions() 🚪✨
        // 這樣不但能合法拿到資料，還能確保內部 Parser 自動被觸發！
        $myPermission = collect($user->getCampPermissions($camp))
            ->where('resource', '\\' . static::class)
            ->firstWhere('action', 'read');

        // 如果連一條讀取學員的權限都沒配給他，直接回傳一个「什麼都撈不到」的邊界條件
        if (!$myPermission) {
            return $query->whereRaw('1 = 0');
        }

        // 3. 根據大腦算出來的 range_parsed 分數（歷史包袱：0全域, 1大組, 2小組, 3個人）
        // 完美轉譯成一槍斃命的 SQL 條件！
        return $query->where('applicants.camp_id', $camp->id)
            ->where(function ($subQuery) use ($user, $camp, $myPermission) {
                switch ($myPermission['range_parsed']) {
                    
                    // 👑 case 0: na & case 4: all -> 不用額外限制，整個營隊隨便看
                    case 0:
                    case 4:
                        return $subQuery;

                    // 大組 (volunteer_large_group) -> 只能看自己大組(section)的學員
                    case 1:
                        if (!$camp->isVcamp) {
                            //學員營隊沒有這個選項，什麼都不給看
                            return $subQuery->whereRaw('1 = 0');
                        }
                        $sectionIds = $user->getAccessibleSections($camp)->pluck('id')->toArray();
                        
                    // 👥 case 2: 小組 (learner_group) -> 只能看同一個 group_id 的學員
                    case 2:
                        $myGroupIds = $user->getCampRoles($camp)->pluck('group_id')->filter();
                        return $subQuery->whereIn('group_id', $myGroupIds);

                    // 📄 case 3: 個人 (person) -> 只能看自己負責直接關懷的學員
                    case 3:
                        // 假設你的 User 關聯 caresLearners 撈出來的是學員的 ID 清單
                        $myCaredLearnerIds = $user->caresLearners()->pluck('id');
                        return $subQuery->whereIn('id', $myCaredLearnerIds);

                    // 🛑 安全降落：不認得的範圍就什麼都不給看
                    default:
                        return $subQuery->whereRaw('1 = 0');
                }
            });
    }

}
