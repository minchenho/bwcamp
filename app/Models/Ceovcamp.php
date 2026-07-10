<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ceovcamp extends Model
{
    //
    protected $table = 'ceovcamp';
    protected $primaryKey = 'applicant_id';
    public $incrementing = false;
    public $resourceNameInMandarin = '菁英營義工特殊欄位';

    protected $fillable = [
        'applicant_id', 'group_priority1', 'group_priority2', 'group_priority3',
        'lrclass_level', 'lrclass', 'cadre_experiences', 'volunteer_experiences', 'transport', 'transport_other',
        'expertise', 'expertise_other', 'language', 'language_other',
        'unit', 'industry', 'title', 'job_property', 'employees', 'direct_managed_employees',
        'capital', 'org_type', 'years_operation'
    ];
    
    /**
     * 🚀 建議補上：與學員主表的直系關聯
     * 用法：$ecamp->applicant
     */
    public function applicant()
    {
        return $this->belongsTo(Applicant::class, 'applicant_id', 'id');
    }
}
