<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mvcamp extends Model
{
    //利用self_intro快速篩出義工窗口
    //第一年舉辦；暫時的solution
    public const DESCRIPTION_UNIFIED_CONTACT = '第5組義工窗口';

    protected $table = 'mvcamp';
    protected $primaryKey = 'applicant_id';
    public $incrementing = false;
    public $resourceNameInMandarin = '醫事人員營義工特殊欄位';

    protected $fillable = [
        'applicant_id', 'lrclass', 'self_intro'
    ];

    /**
     * 🚀 建議補上：與學員主表的直系關聯
     * 用法：$mvcamp->applicant
     */
    public function applicant()
    {
        return $this->belongsTo(Applicant::class, 'applicant_id', 'id');
    }
}
