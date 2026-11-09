<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 新增區域：
 * - 「未分區」：系統保底，報名時算出的區域不在營隊設定的區域（region_camp_xref）中時使用
 * - 「高區」：tcamp 改用北區／中區／高區（北區、中區已存在）
 */
return new class extends Migration
{
    private const NAMES = ['未分區', '高區'];

    public function up()
    {
        foreach (self::NAMES as $name) {
            if (!DB::table('regions')->where('name', $name)->exists()) {
                DB::table('regions')->insert(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down()
    {
        // 已有學員或職務使用時不刪除
        foreach (self::NAMES as $name) {
            $id = DB::table('regions')->where('name', $name)->value('id');
            if ($id && !DB::table('applicants')->where('region_id', $id)->exists()
                && !DB::table('camp_orgs')->where('region_id', $id)->exists()) {
                DB::table('regions')->where('id', $id)->delete();
            }
        }
    }
};
