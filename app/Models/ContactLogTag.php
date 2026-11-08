<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 關懷記錄標籤：依營隊類型（camp_table）共用，由營隊管理者在營隊工具箱設定
 */
class ContactLogTag extends Model
{
    protected $table = 'contact_log_tag';

    // 標籤清單改由營隊工具箱管理，不列入權限矩陣（getAvailableModels 會略過名稱為空的 Model）
    public $resourceNameInMandarin = '';
    public $resourceDescriptionInMandarin = '';

    protected $fillable = [
        'camp_table', 'tag', 'color', 'notes'
    ];

    /**
     * 標籤顏色：key => [名稱, 背景色, 文字色]；新標籤依序取用目前同類營隊用得最少的顏色
     */
    public const COLORS = [
        'blue'   => ['藍', '#2563eb', '#fff'],
        'green'  => ['綠', '#15803d', '#fff'],
        'orange' => ['橘', '#c2410c', '#fff'],
        'purple' => ['紫', '#7c3aed', '#fff'],
        'red'    => ['紅', '#dc2626', '#fff'],
        'teal'   => ['藍綠', '#0f766e', '#fff'],
        'pink'   => ['粉紅', '#db2777', '#fff'],
        'indigo' => ['靛', '#4338ca', '#fff'],
        'amber'  => ['黃', '#fbbf24', '#1f2937'],
        'cyan'   => ['淺藍', '#67e8f9', '#1f2937'],
        'olive'  => ['橄欖綠', '#65a30d', '#fff'],
        'slate'  => ['灰', '#475569', '#fff'],
    ];

    /**
     * 徽章的 inline style
     */
    public function getBadgeStyleAttribute(): string
    {
        [, $background, $text] = self::COLORS[$this->color] ?? self::COLORS['slate'];
        return "background-color: {$background}; color: {$text};";
    }

    /**
     * 某營隊類型下一個新標籤的顏色：用得最少的顏色中，排在調色盤最前面的
     */
    public static function nextColorFor(string $campTable): string
    {
        $used = static::where('camp_table', $campTable)->pluck('color')->countBy();
        return collect(array_keys(self::COLORS))
            ->sortBy(fn ($key, $index) => [$used->get($key, 0), $index])
            ->first();
    }

    public function logs(): BelongsToMany
    {
        return $this->belongsToMany(ContactLog::class, 'contact_log_tag_xref', 'tag_id', 'log_id')->withTimestamps();
    }

    /**
     * 某營隊類型可用的標籤
     */
    public function scopeForCampTable($query, string $campTable)
    {
        return $query->where('camp_table', $campTable)->orderBy('tag');
    }
}
