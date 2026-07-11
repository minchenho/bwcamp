<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 執行架構升級、洗舊資料、重新規劃索引
     */
    public function up(): void
    {
        // === 第一階段：欄位調整與清除無效索引 ===
        Schema::table('camp_org', function (Blueprint $table) {
            // 1. 新增 depth 欄位，預設為 0 (Root)
            if (!Schema::hasColumn('camp_org', 'depth')) {
                $table->tinyInteger('depth')->unsigned()->notNull()->default(0)->after('position');
            }
            
            // 2. 校正 batch_id 型態為標準的 bigint unsigned 並允許為空 (與梯次表主鍵對齊)
            $table->unsignedBigInteger('batch_id')->nullable()->change();

            // 3. 拔除原始 DDL 中重複且無效的索引，精簡索引樹體積
            $table->dropIndex('camp_org_camp_id_foreign'); 
            $table->dropIndex('camp_org_id_camp_id_index');
        });

        // === 第二階段：安全資料移轉與清洗 (Data Migration) ===
        // 在不依賴舊 section 欄位的前提下，完全利用實體外鍵關係鏈在記憶體中推算真實的 depth。
        // 同時將原本 all_group = 1 的全域邏輯，無縫轉移至 group_id = 0。
        $orgMap = DB::table('camp_org')
            ->select('id', 'prev_id', 'position', 'all_group', 'group_id')
            ->get()
            ->keyBy('id')
            ->toArray();

        // 採用分批（Chunk）處理，防止大型營隊的歷史髒資料導致記憶體或資料庫鎖定炸裂
        DB::table('camp_org')->orderBy('id')->chunkById(100, function ($orgs) use ($orgMap) {
            foreach ($orgs as $org) {
                // --- 邏輯 A：推算真實 depth ---
                $depth = 0;
                $currentPrevId = $org->prev_id;

                if (empty($currentPrevId) || $currentPrevId == 0) {
                    // 檢查這筆資料是否真的是頂層主管（職稱包含總協、大會）
                    $isRealRoot = str_contains($org->position ?? '', '大會');
                    // 如果是真 Boss 層級為 0；若是落單、沒填到上級的基層孤兒資料，強制歸類在第 1 層大組級，防污染第 0 層
                    $depth = $isRealRoot ? 0 : 1; 
                } else {
                    $safetyLoop = 0; 
                    // 安全防線：最多只往上爬 20 層，防止舊資料有 A->B->A 的無限死循環
                    while ($currentPrevId && $currentPrevId != 0 && isset($orgMap[$currentPrevId]) && $safetyLoop < 20) {
                        $depth++;
                        $currentPrevId = $orgMap[$currentPrevId]->prev_id;
                        $safetyLoop++;
                    }
                }

                // --- 邏輯 B：轉移 all_group 到 group_id 狀態機 ---
                $newGroupId = $org->group_id;
                // 如果舊資料標記了 all_group = 1，依新架構約定將 group_id 強制校正為 0（全域管轄）
                if ($org->all_group == 1) {
                    $newGroupId = 0;
                }

                // 將精準洗好的 depth 與轉移後的 group_id 一口氣更新
                DB::table('camp_org')
                    ->where('id', $org->id)
                    ->update([
                        'depth' => $depth,
                        'group_id' => $newGroupId
                    ]);
            }
        });

        // === 第三階段：拔除舊欄位，補上全新商業邏輯的關鍵索引 ===
        Schema::table('camp_org', function (Blueprint $table) {
            // 拔除冗餘、反正規化的舊欄位
            $table->dropColumn(['section', 'is_node', 'all_group']);

            // 【優化核心】在保留原有 camp_id 索引的同時，補齊以下三個核心索引：
            $table->index('depth', 'camp_org_depth_index');       // 大幅加速「按官階/層級篩選」
            $table->index('prev_id', 'camp_org_prev_id_index');   // 大幅加速「樹狀結構向下遞迴尋找子節點」
            $table->index('batch_id', 'camp_org_batch_id_index'); // 大幅加速「前台頻繁切換梯次顯示職務」
        });
    }

    /**
     * Reverse the migrations.
     * 安全回滾邏輯
     */
    public function down(): void
    {
        Schema::table('camp_org', function (Blueprint $table) {
            // 1. 移除新加的三個高效索引
            $table->dropIndex('camp_org_depth_index');
            $table->dropIndex('camp_org_prev_id_index');
            $table->dropIndex('camp_org_batch_id_index');

            // 2. 把舊欄位加回來
            // 提示：這裡必須設為 nullable()，避免因為沒有舊資料而造成回滾失敗
            $table->string('section', 255)->nullable()->after('position');
            $table->bigInteger('is_node')->unsigned()->nullable()->after('group_id');
            $table->tinyInteger('all_group')->default(0)->after('group_id');

            // 3. 恢復舊型態與原始 DDL 的索引
            $table->integer('batch_id')->nullable()->change();
            $table->index('camp_id', 'camp_org_camp_id_foreign');
            $table->index(['id', 'camp_id'], 'camp_org_id_camp_id_index');

            // 4. 移除新架構的 depth
            $table->dropColumn('depth');
        });
    }
};
