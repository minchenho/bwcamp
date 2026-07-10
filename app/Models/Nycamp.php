<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nycamp extends Model
{
    //
    protected $table = 'nycamp';
    protected $primaryKey = 'applicant_id';
    public $incrementing = false;

    public $resourceNameInMandarin = '國際青年營特殊欄位';

    protected $fillable = [
        'applicant_id', 'chinese_first_name', 'chinese_last_name', 'english_last_name', 'language',
        'addr_city', 'addr_state', 'addr_country', 'is_student', 'school', 'department', 'grade', 'unit', 'title',
        'dietary_needs', 'other_needs', 'accommodation_needs', 'companion_name', 'companion_as_roommate', 'motivation', 'info_source'
    ];

    /**
     * 🚀 建議補上：與學員主表的直系關聯
     * 用法：$nycamp->applicant
     */
    public function applicant()
    {
        return $this->belongsTo(Applicant::class, 'applicant_id', 'id');
    }
}
