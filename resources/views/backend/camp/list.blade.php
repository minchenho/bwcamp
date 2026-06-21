@extends('backend.master')
@php
    // 自動計算近三年的年份陣列
    $currentYear = (int)date('Y');
    $recentYears = [$currentYear, $currentYear - 1, $currentYear - 2];
    $recentYearsJson = json_encode($recentYears);

    // ==========================================
    // ✨ 稍後在這邊填入你想控制的欄位 Class 名稱
    // ==========================================
    
    // 「部分（精簡）」要顯示的欄位 (其餘會被隱藏)
    $partialColumns = [
        'col-id',
        'col-fullname',
        'col-abbreviation',
        'col-table',
        'col-reg-start',
        'col-reg-end',
        'col-actions' // 最後的操作按鈕通常建議保留
    ];

    // 「全部」要顯示的欄位 (通常就是所有的欄位)
    $allColumns = [
        'col-id',
        'col-fullname',
        'col-abbreviation',
        'col-table',
        'col-reg-start',
        'col-reg-end',
        'col-announce',
        'col-confirm',
        'col-reply',
        'col-final-end',
        'col-modify',
        'col-cancel',
        'col-actions'
    ];

    // 轉成 JSON 讓 JavaScript 可以讀取
    $partialColumnsJson = json_encode($partialColumns);
    $allColumnsJson = json_encode($allColumns);
@endphp

@section('content')
    <h2 class="d-inline-block">營隊列表</h2>
    <a href="{{ route('showAddCamp') }}" class="btn btn-success d-inline-block" style="margin-bottom: 10px">建立營隊</a>
    
    @if(\Session::has('message'))
        <div class='alert alert-success' role='alert'>
            {{ \Session::get('message') }}
        </div>
    @endif

    <div class="mb-1 rounded" style="background-color: #f8f9fa; padding: 8px 0px 8px 0px; margin-bottom: 10px;">
        <span style="margin-right: 30px;">
            <label style="margin-right: 10px; font-weight: bold; padding-left: 0;">營隊範圍：</label>
            <label style="margin-right: 15px; cursor: pointer;">
                <input type="radio" name="year_filter" value="recent" checked onchange="applyFilters()"> 近三年營隊 ({{ implode('、', array_reverse($recentYears)) }})
            </label>
            <label style="cursor: pointer;">
                <input type="radio" name="year_filter" value="all" onchange="applyFilters()"> 全部營隊
            </label>
        </span>
        <br>
        <span>
            <label style="margin-right: 10px; font-weight: bold;">顯示欄位：</label>
            <label style="margin-right: 15px; cursor: pointer;">
                <input type="radio" name="column_filter" value="partial" checked onchange="applyFilters()"> 部分欄位
            </label>
            <label style="cursor: pointer;">
                <input type="radio" name="column_filter" value="all" onchange="applyFilters()"> 全部欄位
            </label>
        </span>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="thead-light">
                <tr>
                    {{-- ✨ 每個 <th> 和 <td> 都加上對應的 col-xxx class --}}
                    <th class="col-id">ID</th>
                    <th scope="col" class="text-nowrap col-fullname">營隊全名</th>
                    <th scope="col" class="text-nowrap col-abbreviation">營隊簡稱</th>
                    <th scope="col" class="text-nowrap col-table">資料表<br>名稱</th>
                    <th scope="col" class="text-nowrap col-reg-start">報名<br>開始日</th>
                    <th scope="col" class="text-nowrap col-reg-end">報名<br>結束日</th>
                    <th scope="col" class="text-nowrap col-announce">錄取<br>公佈日</th>
                    <th scope="col" class="text-nowrap col-confirm">回覆<br>參加<br>截止日</th>
                    <th scope="col" class="text-nowrap col-reply">是否需<br>回覆<br>參加</th>
                    <th scope="col" class="text-nowrap col-final-end">後台<br>報名<br>結束日</th>
                    <th scope="col" class="text-nowrap col-modify">報名資料<br>修改<br>截止日</th>
                    <th scope="col" class="text-nowrap col-cancel">取消<br>截止日</th>
                    <th scope="col" class="text-nowrap col-actions" style="min-width: 280px;">編輯營隊<br>梯次與組織</th>
                </tr>
            </thead>
            <tbody>
                @foreach($camps as $camp)
                    <tr class="camp-row" data-year="{{ $camp->year }}" @if($camp->test) style="background: #83BFF3; color: white;" @endif>
                        <td class="col-id">{{ $camp->id }}</td>
                        <td class="col-fullname">{{ $camp->fullName }}</td>
                        <td class="col-abbreviation">{{ $camp->abbreviation }}</td>
                        <td class="col-table">{{ $camp->table }}</td>
                        <td class="col-reg-start">{{ $camp->registration_start ?? '' }}</td>
                        <td class="col-reg-end">{{ $camp->registration_end ?? '' }}</td>
                        <td class="col-announce">{{ $camp->admission_announcing_date ?? '' }}</td>
                        <td class="col-confirm">{{ $camp->admission_confirming_end ?? '' }}</td>
                        <td class="col-reply">{{ $camp->needed_to_reply_attend }}</td>
                        <td class="col-final-end">{{ $camp->final_registration_end ?? '' }}</td>
                        <td class="col-modify">{{ $camp->modifying_deadline ?? '' }}</td>
                        <td class="col-cancel">{{ $camp->cancellation_deadline ?? '' }}</td>
                        <td class="text-nowrap col-actions">
                            <a href="{{ route('showModifyCamp', $camp->id) }}" class="btn btn-primary btn-sm">編輯營隊</a><br>
                            <a href="{{ route('showBatch', $camp->id) }}" class="btn btn-success btn-sm" target="_blank">梯次列表</a><br>
                            @if(!str_contains($camp->table, 'vcamp'))
                                <a href="{{ route('showOrgs', $camp->id) }}" class="btn btn-secondary btn-sm">組織列表</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

<script>
// 將原本的 filterCampTable 改成整合型的 applyFilters，一次處理「年份」與「欄位」
function applyFilters() {
    // -----------------------------------------------------
    // 1. 處理年份篩選 (Row 隱藏)
    // -----------------------------------------------------
    const recentYears = {!! $recentYearsJson !!}.map(Number);
    const yearFilter = document.querySelector('input[name="year_filter"]:checked').value;
    const rows = document.querySelectorAll('.camp-row');
    
    rows.forEach(row => {
        const campYear = Number(row.getAttribute('data-year'));
        if (yearFilter === 'recent' && !recentYears.includes(campYear)) {
            row.style.display = 'none';
        } else {
            row.style.display = '';
        }
    });

    // -----------------------------------------------------
    // 2. 處理欄位篩選 (Column 隱藏)
    // -----------------------------------------------------
    const partialCols = {!! $partialColumnsJson !!};
    const allCols = {!! $allColumnsJson !!};
    const columnFilter = document.querySelector('input[name="column_filter"]:checked').value;

    // 決定目前哪些 class 應該被顯示
    const targetVisibleCols = (columnFilter === 'partial') ? partialCols : allCols;

    // 撈出表格中所有有定義 col- 開頭的 th 和 td
    // 這裡我們直接用 allCols 當作完整的母體清單來掃描
    allCols.forEach(colClass => {
        const elements = document.querySelectorAll('.' + colClass);
        
        elements.forEach(el => {
            if (targetVisibleCols.includes(colClass)) {
                el.style.display = ''; // 顯示該欄位
            } else {
                el.style.display = 'none'; // 隱藏該欄位
            }
        });
    });
}

// 網頁載入完成後先自動執行一次
document.addEventListener("DOMContentLoaded", function() {
    applyFilters();
});
</script>
@endsection