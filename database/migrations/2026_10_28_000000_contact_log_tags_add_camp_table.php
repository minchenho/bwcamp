<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 關懷記錄標籤：依營隊類型（camp_table）共用；同一筆記錄同一標籤只連結一次
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('contact_log_tag', function (Blueprint $table) {
            $table->string('camp_table', 20)->nullable()->after('id')->index();
        });
        Schema::table('contact_log_tag_xref', function (Blueprint $table) {
            $table->unique(['log_id', 'tag_id']);
            $table->index('tag_id');
        });
    }

    public function down()
    {
        Schema::table('contact_log_tag_xref', function (Blueprint $table) {
            $table->dropUnique(['log_id', 'tag_id']);
            $table->dropIndex(['tag_id']);
        });
        Schema::table('contact_log_tag', function (Blueprint $table) {
            $table->dropIndex(['camp_table']);
            $table->dropColumn('camp_table');
        });
    }
};
