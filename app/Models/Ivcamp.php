<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ivcamp extends Model
{
    //
    protected $table = 'ivcamp';
    protected $primaryKey = 'applicant_id';
    public $incrementing = false;
    public $resourceNameInMandarin = '國際事務處營隊義工特殊欄位';

    protected $fillable = [
        'applicant_id', 'group_priority1', 'lrclass', 'expertise', 'expertise_other', 'self_intro'
    ];

    protected $guarded = [];

    /**
     * 🚀 建議補上：與學員主表的直系關聯
     * 用法：$ivcamp->applicant
     */
    public function applicant()
    {
        return $this->belongsTo(Applicant::class, 'applicant_id', 'id');
    }
}
