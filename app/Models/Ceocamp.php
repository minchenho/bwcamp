<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ceocamp extends Model
{
    protected $table = 'ceocamp';
    protected $primaryKey = 'applicant_id';
    public $incrementing = false;
    public $resourceNameInMandarin = '菁英營特殊欄位';

    protected $fillable = [
        'applicant_id', 'unit', 'title', 'job_property', 'job_property_other',
        'employees', 'direct_managed_employees', 'capital', 'capital_unit', 'industry', 'industry_other',
        'org_type', 'org_type_other', 'years_operation', 'contact_time', 'marital_status',
        'exceptional_conditions', 'participation_mode', 'reasons_online', 'reasons_recommend',
        'substitute_name', 'substitute_phone', 'substitute_email', 'is_lrclass', 'lrclass'
    ];

    protected $guarded = [];

    protected $appends = [
        //'contact_time_csv',
    ];

    /**
     * 🚀 建議補上：與學員主表的直系關聯
     * 用法：$ceocamp->applicant
     */
    public function applicant()
    {
        return $this->belongsTo(Applicant::class, 'applicant_id', 'id');
    }

    /*
     * 取得 contact_time 的 CSV 格式
     */
    protected function contactTimeCsv(): Attribute
    {
        return Attribute::make(
            get: function () {
                $trimmed = trim($this->contact_time ?? '', '||/');
                return str_replace('||/', ',', $trimmed);
            },
        );
    }

}
