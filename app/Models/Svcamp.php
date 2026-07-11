<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Svcamp extends Model
{
    //
    protected $table = 'svcamp';
    protected $primaryKey = 'applicant_id';
    public $incrementing = false;
    public $resourceNameInMandarin = '永續課程義工特殊欄位';

    protected $fillable = [
        'applicant_id', 'lrclass', 'self_intro'
    ];
    
    /**
     * 🚀 建議補上：與學員主表的直系關聯
     * 用法：$svcamp->applicant
     */
    public function applicant()
    {
        return $this->belongsTo(Applicant::class, 'applicant_id', 'id');
    }
}
