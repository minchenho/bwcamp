<?php

namespace App\Http\Controllers;

use App\Models\ApplicantsGroup;
use App\Models\Batch;
use App\Models\Camp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 組別編製：管理各梯次的學員組別（applicants_groups），梯次的 num_groups 會與實際組數同步
 */
class ApplicantsGroupController extends Controller
{
    private const MAX_GROUPS = 200;

    public function showGroups($camp_id, $batch_id = null)
    {
        $camp = Camp::findOrFail($camp_id);
        $batches = $camp->batches;
        $batch = $batch_id ? $batches->firstWhere('id', (int) $batch_id) : $batches->first();
        if ($batch_id && !$batch) {
            abort(404);
        }
        $groups = $batch
            ? $batch->groups()->withCount(['applicants', 'camp_orgs', 'numbers'])->orderBy('id')->get()
            : collect();
        $groupCounts = ApplicantsGroup::whereIn('batch_id', $batches->pluck('id'))
            ->selectRaw('batch_id, count(*) as n')->groupBy('batch_id')->pluck('n', 'batch_id');
        return view('backend.camp.groupList', compact('camp', 'batches', 'batch', 'groups', 'groupCounts'));
    }

    /**
     * 依「簡單」（前綴 + 編號 + 後綴）或「客製」（一行一組）方式建立組別
     */
    public function generateGroups(Request $request, $camp_id, $batch_id)
    {
        $batch = $this->findBatch($camp_id, $batch_id);
        $data = $request->validate([
            'mode' => 'required|in:simple,custom',
            'count' => 'required_if:mode,simple|nullable|integer|min:1|max:' . self::MAX_GROUPS,
            'prefix' => 'nullable|string|max:50',
            'suffix' => 'nullable|string|max:50',
            'numeral' => 'required_if:mode,simple|in:arabic,chinese',
            'pad' => 'nullable|integer|min:0|max:5',
            'start' => 'required_if:mode,simple|nullable|integer|min:1|max:9999',
            'names' => 'required_if:mode,custom|nullable|string',
            'category' => 'nullable|string|max:255',
        ], [], [
            'mode' => '編製方式', 'count' => '組數', 'prefix' => '前綴', 'suffix' => '後綴',
            'numeral' => '編號格式', 'pad' => '補零位數', 'start' => '起始號碼', 'names' => '組別名單', 'category' => '分類',
        ]);

        $aliases = $data['mode'] === 'simple' ? $this->simpleAliases($data) : $this->customAliases($data['names']);
        if (!$aliases) {
            return back()->withInput()->with('error', '沒有可建立的組別。');
        }
        if (count($aliases) > self::MAX_GROUPS) {
            return back()->withInput()->with('error', '一次最多建立 ' . self::MAX_GROUPS . ' 組。');
        }
        $duplicated = array_unique(array_diff_assoc($aliases, array_unique($aliases)));
        if ($duplicated) {
            return back()->withInput()->with('error', '名單中有重複的組名：' . implode('、', $duplicated));
        }
        $existing = $batch->groups()->whereIn('alias', $aliases)->pluck('alias');
        if ($existing->isNotEmpty()) {
            return back()->withInput()->with('error', '以下組名已存在：' . $existing->implode('、'));
        }

        DB::transaction(function () use ($batch, $aliases, $data) {
            foreach ($aliases as $alias) {
                $batch->groups()->create(['alias' => $alias, 'category' => $data['category'] ?? null]);
            }
            $this->syncNumGroups($batch);
        });
        \Session::flash('message', $batch->name . ' 已建立 ' . count($aliases) . ' 個組別。');
        return redirect()->route('showGroups', [$camp_id, $batch->id]);
    }

    /**
     * 批次修改組名與分類
     */
    public function updateGroups(Request $request, $camp_id, $batch_id)
    {
        $batch = $this->findBatch($camp_id, $batch_id);
        $data = $request->validate([
            'groups' => 'required|array',
            'groups.*.alias' => 'required|string|max:255',
            'groups.*.category' => 'nullable|string|max:255',
        ], [], ['groups.*.alias' => '組名', 'groups.*.category' => '分類']);

        $groups = $batch->groups()->get()->keyBy('id');
        $aliases = $groups->map(fn ($group) => $data['groups'][$group->id]['alias'] ?? $group->alias);
        $duplicated = $aliases->duplicates()->unique();
        if ($duplicated->isNotEmpty()) {
            return back()->withInput()->with('error', '組名不能重複：' . $duplicated->implode('、'));
        }

        $changed = 0;
        DB::transaction(function () use ($groups, $data, &$changed) {
            foreach ($data['groups'] as $id => $fields) {
                $group = $groups->get($id);
                if (!$group) {
                    continue;
                }
                $group->fill(['alias' => $fields['alias'], 'category' => $fields['category'] ?? null]);
                if ($group->isDirty()) {
                    $group->save();
                    $changed++;
                }
            }
        });
        \Session::flash('message', $changed ? "已更新 {$changed} 個組別。" : '沒有需要更新的組別。');
        return redirect()->route('showGroups', [$camp_id, $batch->id]);
    }

    /**
     * 刪除組別：學員、義工職務、座號都沒有時才可刪除
     */
    public function removeGroup(Request $request, $camp_id, $batch_id)
    {
        $batch = $this->findBatch($camp_id, $batch_id);
        $group = $batch->groups()->withCount(['applicants', 'camp_orgs', 'numbers'])->findOrFail($request->input('group_id'));
        if ($group->applicants_count || $group->camp_orgs_count || $group->numbers_count) {
            return back()->with('error', "「{$group->alias}」還有學員、義工職務或座號，不能刪除。");
        }
        DB::transaction(function () use ($batch, $group) {
            $group->delete();
            $this->syncNumGroups($batch);
        });
        \Session::flash('message', "已刪除「{$group->alias}」。");
        return redirect()->route('showGroups', [$camp_id, $batch->id]);
    }

    private function findBatch($camp_id, $batch_id): Batch
    {
        return Batch::where('camp_id', $camp_id)->findOrFail($batch_id);
    }

    private function syncNumGroups(Batch $batch): void
    {
        $batch->update(['num_groups' => $batch->groups()->count()]);
    }

    private function simpleAliases(array $data): array
    {
        $aliases = [];
        for ($i = 0; $i < $data['count']; $i++) {
            $n = $data['start'] + $i;
            $number = $data['numeral'] === 'chinese'
                ? $this->chineseNumber($n)
                : str_pad((string) $n, (int) ($data['pad'] ?? 0), '0', STR_PAD_LEFT);
            $aliases[] = ($data['prefix'] ?? '') . $number . ($data['suffix'] ?? '');
        }
        return $aliases;
    }

    private function customAliases(string $names): array
    {
        return collect(preg_split('/\R/u', $names))->map(fn ($line) => trim($line))->filter()->values()->all();
    }

    /**
     * 1～9999 轉中文數字：一、十、十一、二十一、一百零一
     */
    private function chineseNumber(int $n): string
    {
        $digits = ['零', '一', '二', '三', '四', '五', '六', '七', '八', '九'];
        $units = ['', '十', '百', '千'];
        $str = (string) $n;
        $len = strlen($str);
        $result = '';
        $pendingZero = false;
        for ($i = 0; $i < $len; $i++) {
            $digit = (int) $str[$i];
            if ($digit === 0) {
                $pendingZero = true;
                continue;
            }
            if ($pendingZero) {
                $result .= '零';
                $pendingZero = false;
            }
            $result .= $digits[$digit] . $units[$len - $i - 1];
        }
        // 10～19 習慣寫「十、十一」而非「一十、一十一」
        return $len === 2 && str_starts_with($result, '一十') ? mb_substr($result, 1) : $result;
    }
}
