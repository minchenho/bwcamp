<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
// 💡 【修正點 1】必須主動引入 Attribute，否則會噴類別不存在錯誤
use Illuminate\Database\Eloquent\Casts\Attribute;

class Ecamp extends Model
{
    protected $table = 'ecamp';
    protected $primaryKey = 'applicant_id';
    public $incrementing = false;
    
    public $resourceNameInMandarin = '企業營特殊欄位';

    protected $fillable = [
        'applicant_id', 'belief', 'education', 'unit', 'unit_location',
        'title', 'level', 'job_property', 'experience', 'employees',
        'direct_managed_employees', 'industry', 'info_source', 'info_source_other',
        'is_membership', 'after_camp_available_day', 'favored_event', 'created_at'
    ];

    protected $casts = [];

    /**
     * 🚀 建議補上：與學員主表的直系關聯
     * 用法：$ecamp->applicant
     */
    public function applicant()
    {
        return $this->belongsTo(Applicant::class, 'applicant_id', 'id');
    }


    protected function infoSource(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                return str_replace(',', "||/", $value);
            },
            get: fn ($value) => $value
        );
    }


    /**
     * 訊息來源存取器
     */
/*
    protected function infoSource(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                // 如果傳進來的是陣列（通常複選欄位是陣列），先用逗號組裝，再換成自訂分隔符
                if (is_array($value)) {
                    $value = implode(',', $value);
                }
                return str_replace(',', "||/", $value);
            },
            // 💡 【優化點 2】在 get 時，自動拆回陣列。
            // 這樣你在前端 Blade 就能直接用 in_array('網路', $ecamp->info_source) 來判定打勾，超級方便！
            get: function ($value) {
                return $value ? explode('||/', $value) : [];
            }
        );
    }
*/
    /**
     * 是否加入會員存取器
     */
    protected function isMembership(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                $valueStr = trim((string)$value);
                if ($valueStr === '1' || $valueStr === '是' || str_contains($valueStr, '立即加入')) {
                    return 1;
                } elseif ($valueStr === '0' || $valueStr === '否' || str_contains($valueStr, '暫時不要')) {
                    return 0;
                } else {
                    return 0;
                }
            },
            get: fn ($value) => $value
        );
    }
}