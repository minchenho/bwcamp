<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hcamp extends Model
{
    //
    protected $table = 'hcamp';
    protected $primaryKey = 'applicant_id';
    public $incrementing = false;
    public $resourceNameInMandarin = '快樂營特殊欄位';

    protected $fillable = [
        'applicant_id', 'education', 'special_condition', 'traffic_depart', 'traffic_return','branch_or_classroom_belongs_to', 'class_type', 'parent_lamrim_class', 'is_recommended_by_reading_class', 'is_lamrim', 'is_child_blisswisdommed'
    ];

    /**
     * 🚀 建議補上：與學員主表的直系關聯
     * 用法：$hcamp->applicant
     */
    public function applicant()
    {
        return $this->belongsTo(Applicant::class, 'applicant_id', 'id');
    }
}
