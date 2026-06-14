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
        Schema::create('camp_custom_options', function (Blueprint $table) {
            $table->id();
            
            // 1. 關聯外鍵：隸屬於哪一個營隊
            $table->unsignedBigInteger('camp_id')->comment('營隊主表 ID');
            
            // 2. 梯次外鍵：允許為 Null。Null 代表「全營隊通用」；有值代表「該梯次專屬客製」
            $table->unsignedBigInteger('batch_id')->nullable()->comment('梯次 ID (Null為全營隊通用)');
            
            // 3. 類別標籤：例如 'industry' (產業別)、'fare_room' (房型費用)、'education' (學歷)
            $table->string('type')->comment('欄位類別標籤');
            
            // 4. 前端顯示的選項文字：例如 '資訊科技業'、'單人房(1大床)'
            $table->string('option_label')->comment('選項中文名稱');
            
            // 5. 欄位內部真實儲存的值（通常與名稱相同，若有特殊代碼可分開，預設與 label 一致）
            $table->string('option_value')->comment('選項真實寫入值');
            
            // 6. 附加金額：允許為 Null。純文字選單（如產業別）填 Null；收費項目填金額（如 3800）
            $table->decimal('amount', 10, 2)->nullable()->comment('加價費用 (Null為不收費)');
            
            // 7. 排序權重：數字越小排越前面，完美控制圖表與表單的輸出順序
            $table->integer('sort_order')->default(0)->comment('排序權重 (由小到大)');
            
            $table->timestamps();

            // 💡 效能與架構防禦：建立複合索引 (Index)
            // 未來前台與統計在撈資料時，全都是用這三個條件做 where，加上索引能確保百萬資料下依然維持微秒級的高速查詢
            $table->index(['camp_id', 'batch_id', 'type'], 'camp_batch_type_index');
            
            // 如果您的專案有標準的 foreign key 约束，可以解開下方註解（注意欄位名要跟您 camps/batchs 表一致）
            // $table->foreign('camp_id')->references('id')->on('camps')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('camp_custom_options');
    }
};
