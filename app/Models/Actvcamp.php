<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Actvcamp extends Model
{
    //
    protected $table = 'actvcamp';
    protected $primaryKey = 'applicant_id';
    public $incrementing = false;
    public $resourceNameInMandarin = '小活動義工特殊欄位';

    protected $fillable = [
        'applicant_id','transportation','self_intro'
    ];

    protected $guarded = [];

    /**
     * 🚀 建議補上：與學員主表的直系關聯
     * 用法：$actvcamp->applicant
     */
    public function applicant()
    {
        return $this->belongsTo(Applicant::class, 'applicant_id', 'id');
    }
}
