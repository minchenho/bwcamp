<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Actcamp extends Model
{
    //
    protected $table = 'actcamp';
    protected $primaryKey = 'applicant_id';
    public $incrementing = false;
    public $resourceNameInMandarin = '小活動特殊欄位';

    protected $fillable = [
        'applicant_id','category','lrclass_year','lrclass_number', 
        'transportation','participants','children_ages'
    ];

    protected $guarded = [];
    
    /**
     * 🚀 建議補上：與學員主表的直系關聯
     * 用法：$actcamp->applicant
     */
    public function applicant()
    {
        return $this->belongsTo(Applicant::class, 'applicant_id', 'id');
    }

}
