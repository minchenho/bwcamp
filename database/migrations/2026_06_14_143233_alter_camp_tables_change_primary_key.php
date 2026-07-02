<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 定義所有需要被改裝的營隊特製表清單
     */
    protected function getCampTables(): array
    {
        return [
            'acamp',    'avcamp',
            'actcamp',  'actvcamp',
            'ceocamp',  'ceovcamp',
            'ecamp',    'evcamp',
            'hcamp',
            'icamp',    'ivcamp',
            'lrcamp',   'lrvcamp',
            'mcamp',    'mvcamp',
            'nycamp',   'nyvcamp',
            'scamp',    'svcamp',
            'tcamp',    'tvcamp',
            'utcamp',   'utvcamp',
            'wcamp',    'wvcamp',
            'ycamp',    'yvcamp',
            'lodging',  'traffic'
        ];
    }

    /**
     * Run the migrations.
     */
    public function up()
    {
        foreach ($this->getCampTables() as $tableName) {
            // 防禦機制：確保這張表真的存在才動手
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    
                    // 1. 安全防禦：如果這張表本來就沒有 id 欄位（可能以前改過），就跳過不處理
                    if (!Schema::hasColumn($tableName, 'id')) {
                        return;
                    }

                    // 2. 移除可能干擾的舊普通索引 (如果以前有單獨對 applicant_id 設過 index)
                    // 註：這行如果沒把握可以留著註解，或確定有建過可以用 $table->dropIndex(['applicant_id']);
                    
                    // 3. 刪除原本的自增主鍵 id 欄位
                    $table->dropColumn('id');

                    // 4. 將 applicant_id 升格為全表唯一的老大：主鍵 (Primary Key)
                    $table->primary('applicant_id');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        foreach ($this->getCampTables() as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    
                    // 防禦機制：如果已經有 id 了，代表根本沒被改過，不需要還原
                    if (Schema::hasColumn($tableName, 'id')) {
                        return;
                    }

                    // 1. 降格：先解除 applicant_id 的主鍵身份
                    $table->dropPrimary(['applicant_id']);

                    // 2. 補回：把自動遞增的 id 補回來，並強迫塞在表的最前面（第一個欄位）
                    $table->id()->first();
                });
            }
        }
    }
};