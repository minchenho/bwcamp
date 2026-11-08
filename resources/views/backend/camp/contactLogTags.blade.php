@extends('backend.master')
@section('content')
    @include('backend.camp._toolboxNav', ['toolTitle' => '關懷記錄標籤'])
    <h2>{{ $camp->abbreviation }} 關懷記錄標籤</h2>
    <p class="text-muted">
        標籤用來分類關懷記錄的內容（例：生活、工作），寫記錄時可複選。同一類營隊（{{ $camp->table }}）共用這組標籤。
    </p>

    @if(\Session::has('error'))
        <div class='alert alert-danger' role='alert'>{{ \Session::get('error') }}</div>
    @endif
    @if(\Session::has('message'))
        <div class='alert alert-success' role='alert'>{{ \Session::get('message') }}</div>
    @endif
    @if($errors->any())
        <div class='alert alert-danger' role='alert'>
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @if($tags->isNotEmpty())
        <h4>現有標籤（{{ $tags->count() }} 個）</h4>
        <form action="{{ route('updateContactLogTags', $camp->id) }}" method="POST">
            @csrf
            <table class="table table-bordered table-sm">
                <tr>
                    <th class="text-nowrap">#</th>
                    <th>標籤名稱</th>
                    <th class="text-nowrap">顏色</th>
                    <th>說明</th>
                    <th class="text-nowrap">使用中記錄</th>
                    <th class="text-nowrap">動作</th>
                </tr>
                @foreach($tags as $tag)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td><input type="text" class="form-control form-control-sm" name="tags[{{ $tag->id }}][tag]" value="{{ old("tags.{$tag->id}.tag", $tag->tag) }}" required maxlength="50"></td>
                        <td class="text-nowrap">
                            @php $selectedColor = old("tags.{$tag->id}.color", $tag->color); @endphp
                            <select class="form-control form-control-sm d-inline-block w-auto tag-color-select" name="tags[{{ $tag->id }}][color]" data-preview="tag-preview-{{ $tag->id }}">
                                @foreach(\App\Models\ContactLogTag::COLORS as $key => [$colorName, $background, $text])
                                    <option value="{{ $key }}" data-style="background-color: {{ $background }}; color: {{ $text }};" @selected($selectedColor === $key)>{{ $colorName }}</option>
                                @endforeach
                            </select>
                            <span class="badge" id="tag-preview-{{ $tag->id }}" style="{{ $tag->badge_style }}">{{ $tag->tag }}</span>
                        </td>
                        <td><input type="text" class="form-control form-control-sm" name="tags[{{ $tag->id }}][notes]" value="{{ old("tags.{$tag->id}.notes", $tag->notes) }}" maxlength="255"></td>
                        <td>{{ $tag->logs_count }}</td>
                        <td>
                            @if(!$tag->logs_count)
                                <button type="button" class="btn btn-danger btn-sm" onclick="removeTag({{ $tag->id }}, @js($tag->tag))">刪除</button>
                            @else
                                <button type="button" class="btn btn-outline-secondary btn-sm" disabled title="已有關懷記錄使用，只能改名">刪除</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
            <h6 class="text-info">＊已有關懷記錄使用的標籤不能刪除，只能改名；改名後，舊記錄會顯示新名稱</h6>
            <button type="submit" class="btn btn-primary mb-4">儲存名稱、顏色與說明</button>
        </form>
        <form id="remove-tag-form" action="{{ route('removeContactLogTag', $camp->id) }}" method="POST" class="d-none">
            @csrf
            <input type="hidden" name="tag_id">
        </form>
    @endif

    <div class="card mb-4">
        <div class="card-header"><strong>新增標籤</strong></div>
        <div class="card-body">
            <form action="{{ route('addContactLogTag', $camp->id) }}" method="POST">
                @csrf
                <div class="form-group row">
                    <label class="col-sm-2 col-form-label" for="tags">標籤名稱</label>
                    <div class="col-sm-6">
                        <textarea class="form-control" id="tags" name="tags" rows="4" required placeholder="一行一個，或用頓號、逗號分隔，例如：&#10;生活、工作、家庭">{{ old('tags') }}</textarea>
                        <small class="form-text text-muted">可一次新增多個；已存在的名稱會略過。顏色會自動配上目前用得最少的，之後可在上方修改。</small>
                    </div>
                </div>
                <button type="submit" class="btn btn-success">新增標籤</button>
            </form>
        </div>
    </div>

    <script>
        // 選顏色時即時更新預覽
        document.querySelectorAll('.tag-color-select').forEach(function (select) {
            const update = function () {
                document.getElementById(select.dataset.preview).style.cssText = select.selectedOptions[0].dataset.style;
            };
            select.addEventListener('change', update);
            update();
        });

        function removeTag(id, name) {
            if (confirm('確認刪除標籤「' + name + '」？')) {
                const form = document.getElementById('remove-tag-form');
                form.tag_id.value = id;
                form.submit();
            }
        }
    </script>
@endsection
