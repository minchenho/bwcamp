<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 關懷記錄標籤加上顏色；既有標籤依營隊類型內的建立順序輪流配色
 */
return new class extends Migration
{
    // 與 App\Models\ContactLogTag::COLORS 的順序相同；migration 不依賴 Model，之後改調色盤也不影響
    private const COLOR_KEYS = ['blue', 'green', 'orange', 'purple', 'red', 'teal', 'pink', 'indigo', 'amber', 'cyan', 'olive', 'slate'];

    public function up()
    {
        Schema::table('contact_log_tag', function (Blueprint $table) {
            $table->string('color', 20)->nullable()->after('tag');
        });

        DB::table('contact_log_tag')->orderBy('id')->get(['id', 'camp_table'])
            ->groupBy('camp_table')
            ->each(function ($tags) {
                foreach ($tags->values() as $index => $tag) {
                    DB::table('contact_log_tag')->where('id', $tag->id)
                        ->update(['color' => self::COLOR_KEYS[$index % count(self::COLOR_KEYS)]]);
                }
            });
    }

    public function down()
    {
        Schema::table('contact_log_tag', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
