@php
    // 找出目前節點底下的直屬子層
    $childNodes = $allOrgs->where('prev_id', $node->id)->sortBy('order');
@endphp

<details {{ $node->depth < 2 ? 'open' : '' }}>
    <summary style="padding-left: {{ $node->depth * 10 }}px;">
        📂 {{ $node->position }} 
        <span class="badge badge-secondary font-weight-normal text-xs ml-2">深度: {{ $node->depth }}</span>
        @if($node->batch)
            <span class="badge badge-info font-weight-normal text-xs">
                {{ $node->batch->isVbatch ? '義工' : '學員' }}
            </span>
        @else
            <span class="badge badge-secondary font-weight-normal text-xs">不限</span>
        @endif
    </summary>
    
    <div class="tree-row" style="padding-left: {{ ($node->depth + 1) * 10 }}px;">
        <div class="flex-grow-1 text-muted">
            <small>ID: {{ $node->id }} | 區域: {{ $node->region?->name ?? '不限' }} | 權限數: {{ $node->permissions->count() }}</small>
            @if($node->permissions->isNotEmpty())
                @php
                    $permissionRanges = [
                        'na' => '不指定',
                        'learner_group' => '限學員小組',
                        'volunteer_large_group' => '限義工大組',
                        'person' => '限個別學員',
                        'all' => '全部',
                    ];
                @endphp
                <details class="mt-1">
                    <summary class="font-weight-normal py-0">檢視權限</summary>
                    <ul class="mb-1 pl-4">
                        @foreach($node->permissions as $permission)
                            <li>
                                {{ $permission->display_name ?: $permission->name }}
                                <span class="badge badge-light">
                                    範圍：{{ $permissionRanges[$permission->range] ?? $permission->range ?? '未設定' }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>
        <div class="text-right">
            <a href="{{ route('showAddOrgs', [$camp->id, $node->id]) }}" class="btn btn-sm btn-success py-0">＋新增子職務</a>
            {{-- 新增：載入或複製權限按鈕 --}}
            <a href="{{ route('copyPermissions', [$camp->id, $node->id]) }}" class="btn btn-sm btn-info py-0">載入或複製權限</a>
            @if($node->depth > 0)
                <a href="{{ route('showModifyOrg', [$camp->id, $node->id]) }}" class="btn btn-sm btn-primary py-0">修改</a>
                <a href="{{ route('duplicateOrg', [$camp->id, $node->id]) }}" class="btn btn-sm btn-warning py-0">複製</a>
            @endif

            @if(!($node->users_count ?? $num_users[$node->id] ?? 0) && $childNodes->isEmpty() && $node->depth > 0)
                <form action="{{ route('removeOrg') }}" method="post" class="d-inline">
                    @csrf
                    <input type="hidden" name="org_id" value="{{ $node->id }}">
                    <button type="button" class="btn btn-sm btn-danger py-0" onclick="confirmdelete(this.closest('form'));">刪除</button>
                </form>
            @endif
        </div>
    </div>

    {{-- 如果它還有子節點，繼續向下遞迴渲染自己 --}}
    @if($childNodes->isNotEmpty())
        @foreach($childNodes as $child)
            @include('..partials.org_tree_node', ['node' => $child, 'allOrgs' => $allOrgs, 'num_users' => $num_users])
        @endforeach
    @endif
</details>