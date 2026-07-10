<?php

namespace App\Services;

use App\Models\Camp;
use App\Models\CampOrg;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CampOrgService
{
    /**
     * 一口氣重構整顆組織樹的 depth（層級深度）
     * 消除舊版重複跑 2 次遞迴與多次 foreach save() 的效能災難
     */
    public function rebuildTree(Collection $orgs): void
    {
        // ✨ 終極改法：利用我們在 Model 封裝好的 isRoot() 進行過濾
        // 它會自動幫你擋掉 null 斷鏈孤兒，精準抓到真正的老祖宗！
        $root = $orgs->filter(function ($org) {
            return $org->isRoot();
        })->first();

        if (!$root) return;

        // 導入 Transaction，保證大批量更新時的資料絕對安全
        DB::transaction(function () use ($orgs, $root) {
            // 啟動單一高效遞迴：只從根節點向下梳理一次
            $this->calculateTreeDepth($orgs, $root);

            // 聰明儲存：利用 Laravel 內建的 isDirty()，只實體儲存真正有被改到資料的 Model
            foreach ($orgs as $org) {
                if ($org->isDirty()) {
                    $org->save();
                }
            }
        });
    }

    /**
     * 【局部優化版】只更新某個節點及其子孫的深度
     */
    public function rebuildSubTree(CampOrg $movedOrg, int $newParentId): void
    {
        // 1. 先找出新爸爸的深度是多少
        $newParent = CampOrg::find($newParentId);
        $baseDepth = $newParent ? $newParent->depth + 1 : 1; // 如果新爸爸剛好不見了，退回 1

        // 2. 撈出這隻營隊所有的組織（用來在記憶體裡找子孫，防止 N+1 爆查資料庫）
        $allOrgs = CampOrg::where('camp_id', $movedOrg->camp_id)->get();

        DB::transaction(function () use ($allOrgs, $movedOrg, $baseDepth) {
            // 3. 發動局部遞迴更新
            $this->updateNodeAndChildrenDepth($allOrgs, $movedOrg, $baseDepth);
        });
    }

    /**
     * 局部遞迴核心
     */
    private function updateNodeAndChildrenDepth(Collection $allOrgs, CampOrg $currentNode, int $currentDepth): void
    {
        // 更新目前這個節點的深度
        $currentNode->depth = $currentDepth;
        if ($currentNode->isDirty('depth')) {
            $currentNode->save();
        }

        // 🔍 只在記憶體裡撈出目前節點的「直屬下線（Children）」
        $children = $allOrgs->where('prev_id', $currentNode->id);

        foreach ($children as $child) {
            // 遞迴往下：深度自動 + 1
            $this->updateNodeAndChildrenDepth($allOrgs, $child, $currentDepth + 1);
        }
    }

    /**
     * 核心優化：單一遞迴計算子節點深度
     * 完全拋棄不穩定的 section 字串比對，100% 依賴實體外鍵關係鏈
     */
    private function calculateTreeDepth(Collection $orgs, CampOrg $parent): void
    {
        // 直接在記憶體內抓出以目前節點為直屬上級的子節點
        $children = $orgs->where('prev_id', $parent->id);

        foreach ($children as $child) {
            // 子節點的深度永遠是直屬父節點 + 1
            $child->depth = $parent->depth + 1;

            // 新版 Model 內建 isLeaf 判斷（透過關係鏈），若它下面還有子層，繼續向下遞迴
            if (!$child->isLeaf()) {
                $this->calculateTreeDepth($orgs, $child);
            }
        }
    }

    /**
     * 複製權限至新營隊
     */
    public function copyPermissions(Camp $campDst, Camp $campSrc, CampOrg $orgDst, CampOrg $orgSrc, array $batchIdMatchList): void
    {
        $permissionsSrc = $orgSrc->permissions;
        if ($permissionsSrc->isEmpty()) return;

        DB::transaction(function () use ($campDst, $campSrc, $orgDst, $permissionsSrc, $batchIdMatchList) {
            $permissionsDst = collect();

            foreach ($permissionsSrc as $permissionSrc) {
                $permissionDst = $permissionSrc->replicate();
                $permissionDst->camp_id = $campDst->id;

                // 安全防禦：防止傳入的對照陣列找不到對應的 batch_id 鍵值而導致崩潰
                if (!is_null($permissionDst->batch_id)) {
                    $permissionDst->batch_id = $batchIdMatchList[$permissionSrc->batch_id] ?? null;
                }

                // 名稱與敘述的營隊縮寫置換
                $permissionDst->display_name = str_replace($campSrc->abbreviation, $campDst->abbreviation, $permissionSrc->display_name);
                $permissionDst->description = str_replace($campSrc->abbreviation, $campDst->abbreviation, $permissionSrc->description);
                $permissionDst->created_at = Carbon::now();
                $permissionDst->save();
                
                $permissionsDst->push($permissionDst);
            }

            // 同步中間表關聯（多對多同步）
            $orgDst->syncPermissions($permissionsDst);
        });
    }
    
    /**
     * 同營隊、同梯次內的職務權限快速複製
     */
    public function duplicatePermissions(CampOrg $orgDst, CampOrg $orgSrc): void
    {
        $permissionsSrc = $orgSrc->permissions;
        if ($permissionsSrc->isEmpty()) return;

        DB::transaction(function () use ($orgDst, $permissionsSrc) {
            $permissionsDst = collect();

            foreach ($permissionsSrc as $permissionSrc) {
                $permissionDst = $permissionSrc->replicate();
                $permissionDst->created_at = Carbon::now();
                $permissionDst->save();
                $permissionsDst->push($permissionDst);
            }

            $orgDst->syncPermissions($permissionsDst);
        });
    }
}