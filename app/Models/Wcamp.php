<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wcamp extends Model
{
    //
    protected $table = 'wcamp';
    protected $primaryKey = 'applicant_id';
    public $incrementing = false;
    public $resourceNameInMandarin = '講師培訓營特殊欄位';

    protected $fillable = [
        'applicant_id', 'lrclass', 'unit', 'title', 'learning_experiences', 'volunteer_experiences', 
        'speak_experiences', 'character', 'potential', 'comments'
    ];

    protected $guarded = [];

    /**
     * 🚀 建議補上：與學員主表的直系關聯
     * 用法：$wcamp->applicant
     */
    public function applicant()
    {
        return $this->belongsTo(Applicant::class, 'applicant_id', 'id');
    }
}

