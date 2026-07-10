<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Avcamp extends Model
{
    //
    protected $table = 'avcamp';
    protected $primaryKey = 'applicant_id';
    public $incrementing = false;
    public $resourceNameInMandarin = '卓青營義工特殊欄位';

    protected $fillable = [
        'applicant_id', 'lrclass_level', 'lrclass'
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
