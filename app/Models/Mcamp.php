<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mcamp extends Model
{
    protected $table = 'mcamp';
    protected $primaryKey = 'applicant_id';
    public $incrementing = false;
    public $resourceNameInMandarin = '醫事人員營特殊欄位';

    protected $fillable = [
        'applicant_id', 'unit', 'title', 'status', 'medical_specialty', 'work_category',
    ];

    /**
     * 🚀 建議補上：與學員主表的直系關聯
     * 用法：$mcamp->applicant
     */
    public function applicant()
    {
        return $this->belongsTo(Applicant::class, 'applicant_id', 'id');
    }
}
