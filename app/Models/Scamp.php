<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Scamp extends Model
{
    //
    protected $table = 'scamp';
    protected $primaryKey = 'applicant_id';
    public $incrementing = false;
    public $resourceNameInMandarin = '永續課程特殊欄位';

    protected $fillable = [
        'applicant_id', 'unit', 'address_work', 'department', 'title', 'seniority', 'way', 'way_other', 'expectation', 'is_allow_informed', 'participation_mode', 'exam_format', 'last5'
    ];

    /**
     * 🚀 建議補上：與學員主表的直系關聯
     * 用法：$scamp->applicant
     */
    public function applicant()
    {
        return $this->belongsTo(Applicant::class, 'applicant_id', 'id');
    }
}

