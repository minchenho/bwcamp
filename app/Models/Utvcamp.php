<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Utvcamp extends Model
{
    //
    protected $table = 'utvcamp';
    protected $primaryKey = 'applicant_id';
    public $incrementing = false;
    public $resourceNameInMandarin = '大專教師營義工特殊欄位';

    protected $fillable = [
        'applicant_id', 'self_intro'
    ];
    
    /**
     * 🚀 建議補上：與學員主表的直系關聯
     * 用法：$utvcamp->applicant
     */
    public function applicant()
    {
        return $this->belongsTo(Applicant::class, 'applicant_id', 'id');
    }
}
