<?php

namespace App\Http\Controllers;

use App\Models\Camp;
use App\Models\ContactLogTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 關懷記錄標籤：同一營隊類型（camp_table）共用一組標籤，由營隊管理者設定
 */
class ContactLogTagController extends Controller
{
    public function showContactLogTags($camp_id)
    {
        $camp = Camp::findOrFail($camp_id);
        $tags = ContactLogTag::forCampTable($camp->table)->withCount('logs')->get();
        return view('backend.camp.contactLogTags', compact('camp', 'tags'));
    }

    public function addContactLogTag(Request $request, $camp_id)
    {
        $camp = Camp::findOrFail($camp_id);
        $data = $request->validate([
            'tags' => 'required|string',
        ], [], ['tags' => '標籤']);

        // 一行一個，或以逗號、頓號分隔
        $names = collect(preg_split('/(?:\R|[,，、])+/u', $data['tags']))->map(fn ($name) => trim($name))->filter()->unique()->values();
        if ($names->isEmpty()) {
            return back()->withInput()->with('error', '請輸入標籤名稱。');
        }
        if ($names->contains(fn ($name) => mb_strlen($name) > 50)) {
            return back()->withInput()->with('error', '標籤名稱最多 50 字。');
        }
        $existing = ContactLogTag::where('camp_table', $camp->table)->whereIn('tag', $names)->pluck('tag');
        $newNames = $names->diff($existing);
        foreach ($newNames as $name) {
            ContactLogTag::create(['camp_table' => $camp->table, 'tag' => $name, 'color' => ContactLogTag::nextColorFor($camp->table)]);
        }

        $message = $newNames->isNotEmpty() ? '已新增標籤：' . $newNames->implode('、') . '。' : '';
        if ($existing->isNotEmpty()) {
            $message .= ' 已存在而略過：' . $existing->implode('、') . '。';
        }
        \Session::flash($newNames->isNotEmpty() ? 'message' : 'error', trim($message));
        return redirect()->route('showContactLogTags', $camp_id);
    }

    /**
     * 批次修改標籤名稱與說明
     */
    public function updateContactLogTags(Request $request, $camp_id)
    {
        $camp = Camp::findOrFail($camp_id);
        $data = $request->validate([
            'tags' => 'required|array',
            'tags.*.tag' => 'required|string|max:50',
            'tags.*.notes' => 'nullable|string|max:255',
            'tags.*.color' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(ContactLogTag::COLORS))],
        ], [], ['tags.*.tag' => '標籤名稱', 'tags.*.notes' => '說明', 'tags.*.color' => '顏色']);

        $tags = ContactLogTag::where('camp_table', $camp->table)->get()->keyBy('id');
        $names = $tags->map(fn ($tag) => trim($data['tags'][$tag->id]['tag'] ?? $tag->tag));
        $duplicated = $names->duplicates()->unique();
        if ($duplicated->isNotEmpty()) {
            return back()->withInput()->with('error', '標籤名稱不能重複：' . $duplicated->implode('、'));
        }

        $changed = 0;
        DB::transaction(function () use ($tags, $data, &$changed) {
            foreach ($data['tags'] as $id => $fields) {
                $tag = $tags->get($id);
                if (!$tag) {
                    continue;
                }
                $tag->fill(['tag' => trim($fields['tag']), 'notes' => $fields['notes'] ?? null, 'color' => $fields['color'] ?? $tag->color]);
                if ($tag->isDirty()) {
                    $tag->save();
                    $changed++;
                }
            }
        });
        \Session::flash('message', $changed ? "已更新 {$changed} 個標籤。" : '沒有需要更新的標籤。');
        return redirect()->route('showContactLogTags', $camp_id);
    }

    /**
     * 刪除標籤：已被關懷記錄使用的不能刪除，只能改名
     */
    public function removeContactLogTag(Request $request, $camp_id)
    {
        $camp = Camp::findOrFail($camp_id);
        $tag = ContactLogTag::where('camp_table', $camp->table)->withCount('logs')->findOrFail($request->input('tag_id'));
        if ($tag->logs_count) {
            return back()->with('error', "「{$tag->tag}」已用於 {$tag->logs_count} 筆關懷記錄，不能刪除，只能改名。");
        }
        $tag->delete();
        \Session::flash('message', "已刪除標籤「{$tag->tag}」。");
        return redirect()->route('showContactLogTags', $camp_id);
    }
}
