<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Icamp extends Model
{
    //
    protected $table = 'icamp';
    protected $primaryKey = 'applicant_id';
    public $incrementing = false;
    public $resourceNameInMandarin = '國際事務處特殊欄位';

    protected $fillable = [
        'applicant_id', 'lrclass', 'passport_expiry_year', 'passport_expiry_month', 'passport_expiry_day', 'participation_mode', 'participation_dates', 'transportation_depart', 'transportation_back', 'transportation_back_location', 'acommodation_needs', 'dietary_needs', 'other_needs', 'questions'
    ];

    protected $guarded = [];

    /**
     * 🚀 建議補上：與學員主表的直系關聯
     * 用法：$icamp->applicant
     */
    public function applicant()
    {
        return $this->belongsTo(Applicant::class, 'applicant_id', 'id');
    }
}
