<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nyvcamp extends Model
{
    //
    protected $table = 'nyvcamp';
    protected $primaryKey = 'applicant_id';
    public $incrementing = false;
    public $resourceNameInMandarin = '國際青年營義工特殊欄位';

    protected $fillable = [
        'applicant_id', 'self_intro'
    ];
    
    /**
     * 🚀 建議補上：與學員主表的直系關聯
     * 用法：$nyvcamp->applicant
     */
    public function applicant()
    {
        return $this->belongsTo(Applicant::class, 'applicant_id', 'id');
    }
}
