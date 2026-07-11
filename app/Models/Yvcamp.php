<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Yvcamp extends Model
{
    //
    protected $table = 'yvcamp';
    protected $primaryKey = 'applicant_id';
    public $incrementing = false;
    public $resourceNameInMandarin = '大專營義工特殊欄位';

    protected $fillable = [
        'applicant_id', 'self_intro'
    ];

    /**
     * 🚀 建議補上：與學員主表的直系關聯
     * 用法：$yvcamp->applicant
     */
    public function applicant()
    {
        return $this->belongsTo(Applicant::class, 'applicant_id', 'id');
    }
}
