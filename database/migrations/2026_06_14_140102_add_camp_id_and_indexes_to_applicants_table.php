<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 階段一：修改資料表結構與索引
        Schema::table('applicants', function (Blueprint $table) {
            // 1. 安全加裝實體欄位
            $table->unsignedBigInteger('camp_id')
                ->nullable() 
                ->after('batch_id')
                ->comment('營隊主表 ID (反正規化冗餘欄位，專為跨表統計與撈取加速)');

            // 2. 建立複合索引
            $table->index(['camp_id', 'deleted_at'], 'idx_camp_active_applicants');

            // 3. 🧹 清理門戶
            $table->dropIndex('applicants_id_batch_id_index');
            $table->dropIndex('applicants_deleted_at_index');
        }); // 👈 💡 【修正點 1】原本漏在這裡，Blueprint 必須在這裡先結束！

        // 階段二：【修正點 3】結構建好後，再獨立出來跑舊資料修補
        if (Schema::hasTable('batchs')) {
            \DB::statement("
                UPDATE applicants 
                JOIN batchs ON batchs.id = applicants.batch_id 
                SET applicants.camp_id = batchs.camp_id 
                WHERE applicants.camp_id IS NULL
            ");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('applicants', function (Blueprint $table) {
            // 1. 先復原原本被刪除的兩個舊索引（確保還原後跟以前一模一樣）
            $table->index(['id', 'batch_id'], 'applicants_id_batch_id_index');
            $table->index('deleted_at', 'applicants_deleted_at_index');

            // 2. 移除新建立的複合索引
            $table->dropIndex('idx_camp_active_applicants');
            
            // 💡 【修正點 2】移除了原本沒建立的 idx_batch_active_applicants 避免報錯
            
            // 3. 移除欄位
            $table->dropColumn('camp_id');
        });
    }
};