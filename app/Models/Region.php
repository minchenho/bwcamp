<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $fillable = ['name'];

    /**
     * 系統保底區域：報名時算出的區域不在營隊設定的區域中時使用；不需勾進營隊的 region_camp_xref
     */
    public const UNASSIGNED = '未分區';

    /**
     * 顯示順序：由北到南，最後是東部、離島、海外。
     * 各營隊用詞不同（台北／北區／北部），意義相同的名稱放在同一組，混用時才會排在一起。
     * 不在清單中的區域排在最後，依 id。
     */
    public const DISPLAY_ORDER = [
        ['北苑'],
        ['台北', '北區', '北部'],
        ['基隆'],
        ['桃園', '桃區'],
        ['新竹', '竹區'],
        ['台中', '中區', '中部'],
        ['雲嘉', '雲林', '嘉義'],
        ['台南', '南區', '南部'],
        ['高雄', '高區', '高屏'],
        ['屏東'],
        ['宜蘭'],
        ['花蓮', '東部'],
        ['金馬'],
        ['海外'],
        ['其他'],
        [self::UNASSIGNED],
    ];

    /**
     * 依 DISPLAY_ORDER 排序（由北到南）
     */
    public function scopeNorthToSouth($query)
    {
        $order = array_merge(...self::DISPLAY_ORDER);
        $placeholders = implode(',', array_fill(0, count($order), '?'));
        $column = $this->getTable() . '.name';
        return $query->orderByRaw("FIELD({$column}, {$placeholders}) = 0", $order)
                     ->orderByRaw("FIELD({$column}, {$placeholders})", $order)
                     ->orderBy($this->getTable() . '.id');
    }
    protected $casts = [
        'id' => 'integer',
        'name' => 'string',
    ];

    public function camps() : \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Camp::class, 'region_camp_xref', 'region_id', 'camp_id');
    }

    public function applicants() : \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Applicant::class);
    }

    public function roles() : \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CampOrg::class);
    }
}
