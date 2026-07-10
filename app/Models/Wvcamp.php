<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wvcamp extends Model
{
    //
    protected $table = 'wvcamp';
    protected $primaryKey = 'applicant_id';
    public $incrementing = false;
    public $resourceNameInMandarin = '講師培訓營義工特殊欄位';

    protected $fillable = [
        'applicant_id', 'lrclass', 'self_intro'
    ];

    /**
     * 🚀 建議補上：與學員主表的直系關聯
     * 用法：$wvcamp->applicant
     */
    public function applicant()
    {
        return $this->belongsTo(Applicant::class, 'applicant_id', 'id');
    }
}
