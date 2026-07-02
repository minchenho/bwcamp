<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lrcamp extends Model
{
    //
    protected $table = 'lrcamp';
    protected $primaryKey = 'applicant_id';
    public $incrementing = false;
    public $resourceNameInMandarin = '研討班特殊欄位';  //copy from ecamp

    protected $fillable = [
        'applicant_id', 'belief', 'education', 'unit', 'unit_location',
        'title', 'level', 'job_property', 'experience', 'employees',
        'direct_managed_employees', 'industry', 'after_camp_available_day', 'favored_event'
    ];

    protected $guarded = [];
    
    /**
     * 🚀 建議補上：與學員主表的直系關聯
     * 用法：$lrcamp->applicant
     */
    public function applicant()
    {
        return $this->belongsTo(Applicant::class, 'applicant_id', 'id');
    }
}
