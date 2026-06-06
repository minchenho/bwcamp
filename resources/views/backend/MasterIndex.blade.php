@extends('backend.master')
@php
    $campLists = [
        'tcamp'   => '教師營',
        'utcamp'  => '大專教師營',
        'ecamp'   => '企業營',
        'ceocamp' => '菁英營',
        'ycamp'   => '大專營',
        'nycamp'  => '國際青年營',
        'acamp'   => '卓青營',
    ];
    $tableListsJson = json_encode(array_keys($campLists));
    
    // 自動計算近三年的年份陣列，例如今年 2026，則為 [2026, 2025, 2024]
    $currentYear = (int)date('Y');
    $recentYears = [$currentYear, $currentYear - 1, $currentYear - 2];
    $recentYearsJson = json_encode($recentYears);
@endphp

@section('content')
    <!-- <h2>{{ config('app.name', 'Laravel') }}</h2> -->
    <h2>請選擇營隊進行作業</h2>
    
    <h5 class="text-secondary">
    <div class="mt-3 mb-3 rounded" style="background-color: #f8f9fa; padding: 8px 4px 0px 8px; border-radius: 1px;">
        <label style="margin-right: 10px; font-weight: bold; padding-left: 0;">營隊範圍篩選：</label>
        <label style="margin-right: 10px; cursor: pointer;">
            <input type="radio" name="year_filter" value="recent" checked onchange="filterCamps()"> 近三年營隊 ({{ implode('、', array_reverse($recentYears)) }})
        </label>
        <label style="margin-right: 10px; cursor: pointer;">
            <input type="radio" name="year_filter" value="all" onchange="filterCamps()"> 全部營隊
        </label>
    </div>
    </h5>

    <!-- <p><span class="text-dark bg-warning">請選擇營隊進行作業：</span</p>  -->

    {{-- 主要分類區塊 --}}
    @foreach ($campLists as $table => $name)
        <div class="camp-group" data-group-table="{{ $table }}">
            <b class="group-title">{{ $name }}</b><br class="group-br"><br class="group-br">
            @foreach ($camps as $camp)
                @if(!str_contains($camp->table, "vcamp") && ($camp->table == $table))
                    <a class="camp-link" data-year="{{ $camp->year }}" href="{{ route('campIndex', $camp->id) }}" @if($camp->test) style="color: #ffabcd" @endif>{{ $camp->fullName }}({{ $camp->abbreviation }})</a><br class="link-br">
                @endif
            @endforeach
            <br class="group-end-br">
        </div>
    @endforeach      

    {{-- 其它營隊區塊 --}}
    <div class="camp-group" data-group-table="other">
        <b class="group-title">其它營隊</b><br class="group-br"><br class="group-br">
        @foreach ($camps as $camp)
            @php
                $isOther = !str_contains($camp->table, "vcamp") && !in_array($camp->table, array_keys($campLists));
            @endphp
            @if($isOther)
                <a class="camp-link other-camp-link" data-year="{{ $camp->year }}" href="{{ route('campIndex', $camp->id) }}" @if($camp->test) style="color: #FFCFE2" @endif>{{ $camp->fullName }}({{ $camp->abbreviation }})</a><br class="link-br">
            @endif
        @endforeach
        <br class="group-end-br">
    </div>

<script>
// 讓 filterCamps 變成全域函式，方便 radio button 的 onchange 呼叫
function filterCamps() {
    // 1. 取得 PHP 傳過來的近三年年份陣列 (例如 [2026, 2025, 2024])
    const recentYears = {!! $recentYearsJson !!}.map(Number);
    
    // 2. 檢查目前使用者選了哪一個 radio
    const filterType = document.querySelector('input[name="year_filter"]:checked').value;

    // 3. 處理所有的營隊超連結
    const campLinks = document.querySelectorAll('.camp-link');
    campLinks.forEach(link => {
        const campYear = Number(link.getAttribute('data-year'));
        const nextBr = link.nextElementSibling; // 取得後面的 <br>

        if (filterType === 'recent') {
            // 如果選「近三年」，但營隊年份不在陣列內 -> 隱藏
            if (!recentYears.includes(campYear)) {
                link.style.display = 'none';
                if (nextBr && nextBr.classList.contains('link-br')) nextBr.style.display = 'none';
            } else {
                link.style.display = '';
                if (nextBr && nextBr.classList.contains('link-br')) nextBr.style.display = '';
            }
        } else {
            // 如果選「全部」 -> 通通顯示
            link.style.display = '';
            if (nextBr && nextBr.classList.contains('link-br')) nextBr.style.display = '';
        }
    });

    // 4. 重新檢查群組，如果群組內沒有可顯示的營隊，就隱藏整個群組標題
    const campGroups = document.querySelectorAll('.camp-group');
    campGroups.forEach(group => {
        // 計算該群組內「目前處於顯示狀態 (display !== 'none')」的營隊數量
        const visibleCamps = Array.from(group.querySelectorAll('.camp-link')).filter(link => link.style.display !== 'none');
        
        const title = group.querySelector('.group-title');
        const brs = group.querySelectorAll('.group-br, .group-end-br');

        if (visibleCamps.length === 0) {
            // 如果一個活著的營隊都沒有，把標題和換行都藏起來
            if (title) title.style.display = 'none';
            brs.forEach(br => br.style.display = 'none');
        } else {
            // 有營隊，就展現出來
            if (title) title.style.display = '';
            brs.forEach(br => br.style.display = '');
        }
    });
}

// 網頁載入完成後先自動執行一次，確保初始畫面正確
document.addEventListener("DOMContentLoaded", function() {
    filterCamps();
});
</script>
@endsection