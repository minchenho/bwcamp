@extends('backend.master')
@section('content')
    <style>
        .card-link {
            color: #3F86FB!important;
        }
        .card-link:hover {
            color: #33B2FF!important;
        }
        /* ✨ 新增：樹狀階層的連結線裝飾 */
        .tree-prefix {
            color: #adb5bd;
            font-family: monospace;
            margin-right: 5px;
        }
        .tree-node-active {
            font-weight: 500;
        }
    </style>

    {{-- 1. 頂部標題優化 --}}
    @if ($org_parent->isRoot())
        <h2>{{ $camp_info->abbreviation }} 組別名單</h2>
    @else
        <h2>{{ $camp_info->abbreviation }} {{ $org_parent->getPathString() }} > {{$org_parent->position}} 組別名單</h2>
    @endif

    {{-- 2. 梯次循環渲染 --}}
    @foreach ($camp_info->batches as $batch)
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fa fa-clock-o"></i> 梯次：{{ $batch->name }}</h5>
            </div>
            <div class="card-body">
                <table class="table table-hover table-bordered mb-0">
                    <thead>
                        <tr class="bg-primary text-white">
                            <th>組織職務架構</th>
                            <th style="width: 180px;" class="text-center">編制總人數 (含下層)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $totalCount = 0;
                            // 這裡假設 $orgs 已經由 Controller 依據 depth, order 排好序了
                            // 為了精準計算該梯次「真正不重複」的總人數，我們只加總最頂層（也就是這包資料裡 depth 最小的節點）
                            $minDepth = $orgs->min('depth');
                        @endphp

                        @foreach ($orgs as $org)
                            @php
                                // ✨ 呼叫剛才寫好的強大 Attribute，自動算好目前+往下所有子孫的加總人數
                                $combinedCount = $org->total_users_count;
                                
                                // 如果是當前最頂層的節點，才被納入「合計」，避免子孫人數被重複重複加總
                                if ($org->depth == $minDepth) {
                                    $totalCount += ($org->users_count ?? $org->users()->count());
                                }
                            @endphp
                            <tr>
                                <td>
                                    {{-- ✨ 樹狀美化核心：依據 depth 產生流暢的縮排空間 --}}
                                    @if ($org->depth > $minDepth)
                                        @for ($i = $minDepth; $i < $org->depth; $i++)
                                            <span class="tree-prefix">&nbsp;&nbsp;&nbsp;&nbsp;</span>
                                        @endfor
                                        <span class="tree-prefix">└─</span>
                                    @endif

                                    {{-- 判斷是否還有子層，決定進入子列表還是直接看名單 --}}
                                    @if (!$org->isLeaf())
                                        <a href="{{ route("showSectionList", [$camp_info->id, $org->id]) }}" class="card-link tree-node-active">
                                            <i class="fa fa-folder-open-o text-warning"></i> {{ $org->position }} 
                                            <small class="text-muted">(進入內部層級)</small>
                                        </a>
                                    @else
                                        <a href="{{ route("showSection", [$camp_info->id, $org->id]) }}" class="card-link">
                                            <i class="fa fa-file-text-o text-info"></i> {{ $org->position }}
                                        </a>
                                    @endif
                                </td>
                                
                                {{-- 顯示往下包含所有子組織的「精準大總計」 --}}
                                <td class="text-center font-weight-bold bg-light">
                                    {{ $combinedCount }} 人
                                </td>
                            </tr>
                        @endforeach

                        {{-- 合計列：顯示該梯次的本體總人數 --}}
                        <tr class="table-success">
                            <td class="text-right"><strong>該層級本體總計：</strong></td>
                            <td class="text-center"><strong>{{ $totalCount }} 人</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
@endsection