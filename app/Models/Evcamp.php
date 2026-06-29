<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evcamp extends Model
{
    //
    protected $table = 'evcamp';
    protected $primaryKey = 'applicant_id';
    public $incrementing = false;
    public $resourceNameInMandarin = '企業營義工特殊欄位';

    protected $fillable = [
        'applicant_id', 'group_priority1', 'group_priority2', 'group_priority3',
        'recruit_channel', 'lrclass', 'trclass', 'trclass_no', 'cadre_experiences', 'volunteer_experiences', 'transport', 'transport_other',
        'expertise', 'expertise_other', 'language', 'language_other', 'participation_dates', 'stay_dates', 'is_preliminaries', 'is_cleanup', 'depart_from', 'depart_from_location',
        'unit', 'industry', 'title', 'job_property', 'employees', 'direct_managed_employees',
        'capital', 'capital_unit', 'org_type', 'years_operation'
    ];

    protected $guarded = [];

    /**
     * 🚀 建議補上：與學員主表的直系關聯
     * 用法：$evcamp->applicant
     */
    public function applicant()
    {
        return $this->belongsTo(Applicant::class, 'applicant_id', 'id');
    }
}
