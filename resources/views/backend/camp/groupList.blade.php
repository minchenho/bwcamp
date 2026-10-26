@extends('backend.master')
@section('content')
    @include('backend.camp._toolboxNav', ['toolTitle' => '組別編製'])
    <h2>{{ $camp->abbreviation }} 組別編製</h2>

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

    @if(!$batch)
        <div class="alert alert-info">此營隊尚未建立梯次，請先到 <a href="{{ route('showBatch', $camp->id) }}">梯次</a> 建立。</div>
    @else
        <ul class="nav nav-tabs mb-3">
            @foreach($batches as $item)
                <li class="nav-item">
                    <a class="nav-link @if($item->id == $batch->id) active @endif" href="{{ route('showGroups', [$camp->id, $item->id]) }}">
                        {{ $item->name }} <span class="badge badge-secondary">{{ $groupCounts[$item->id] ?? 0 }} 組</span>
                    </a>
                </li>
            @endforeach
        </ul>

        @if($groups->isNotEmpty() && $batch->num_groups != $groups->count())
            <div class="alert alert-warning">
                梯次設定的組數（num_groups）為 {{ $batch->num_groups ?? 0 }}，實際有 {{ $groups->count() }} 組。在此頁新增或刪除組別後，組數會自動同步為實際組數。
            </div>
        @endif

        @if($groups->isNotEmpty())
            <h4>現有組別（{{ $groups->count() }} 組）</h4>
            <form action="{{ route('updateGroups', [$camp->id, $batch->id]) }}" method="POST">
                @csrf
                <table class="table table-bordered table-sm">
                    <tr>
                        <th class="text-nowrap">#</th>
                        <th>組名</th>
                        <th>分類</th>
                        <th class="text-nowrap">學員人數</th>
                        <th class="text-nowrap">義工職務</th>
                        <th class="text-nowrap">座號</th>
                        <th class="text-nowrap">動作</th>
                    </tr>
                    @foreach($groups as $group)
                        @php $deletable = !$group->applicants_count && !$group->camp_orgs_count && !$group->numbers_count; @endphp
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td><input type="text" class="form-control form-control-sm" name="groups[{{ $group->id }}][alias]" value="{{ old("groups.{$group->id}.alias", $group->alias) }}" required maxlength="255"></td>
                            <td><input type="text" class="form-control form-control-sm" name="groups[{{ $group->id }}][category]" value="{{ old("groups.{$group->id}.category", $group->category) }}" maxlength="255"></td>
                            <td>{{ $group->applicants_count }}</td>
                            <td>{{ $group->camp_orgs_count }}</td>
                            <td>{{ $group->numbers_count }}</td>
                            <td>
                                @if($deletable)
                                    <button type="button" class="btn btn-danger btn-sm" onclick="removeGroup({{ $group->id }}, @js($group->alias))">刪除</button>
                                @else
                                    <button type="button" class="btn btn-outline-secondary btn-sm" disabled title="還有學員、義工職務或座號">刪除</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
                <h6 class="text-info">＊學員人數、義工職務、座號都必須為 0，才可刪除該組別</h6>
                <button type="submit" class="btn btn-primary mb-4">儲存組名與分類</button>
            </form>
            <form id="remove-group-form" action="{{ route('removeGroup', [$camp->id, $batch->id]) }}" method="POST" class="d-none">
                @csrf
                <input type="hidden" name="group_id">
            </form>
        @endif

        @php
            $isFirst = $groups->isEmpty();
            $defaultCount = $isFirst ? ($batch->num_groups ?: '') : 1;
        @endphp
        <div class="card mb-4">
            <div class="card-header"><strong>{{ $isFirst ? '編製組別' : '新增組別' }}</strong></div>
            <div class="card-body">
                <form id="generate-form" action="{{ route('generateGroups', [$camp->id, $batch->id]) }}" method="POST">
                    @csrf
                    <div class="form-group row">
                        <label class="col-sm-2 col-form-label" for="count">組數</label>
                        <div class="col-sm-3">
                            <input type="number" class="form-control" id="count" name="count" min="1" max="200" value="{{ old('count', $defaultCount) }}">
                        </div>
                        @if($isFirst && $batch->num_groups)
                            <div class="col-sm-7 col-form-label text-muted small">已帶入梯次設定的組數，可修改</div>
                        @endif
                    </div>
                    <div class="form-group row">
                        <div class="col-sm-2 col-form-label">編製方式</div>
                        <div class="col-sm-10 pt-2">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="mode" id="mode-simple" value="simple" @checked(old('mode', 'simple') === 'simple')>
                                <label class="form-check-label" for="mode-simple">簡單（前綴 + 編號 + 後綴）</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="mode" id="mode-custom" value="custom" @checked(old('mode') === 'custom')>
                                <label class="form-check-label" for="mode-custom">客製（貼上名單，一行一組）</label>
                            </div>
                        </div>
                    </div>

                    <div id="simple-fields">
                        <div class="form-group row">
                            <label class="col-sm-2 col-form-label" for="prefix">前綴</label>
                            <div class="col-sm-3"><input type="text" class="form-control" id="prefix" name="prefix" maxlength="50" value="{{ old('prefix') }}" placeholder="例：A、第"></div>
                            <label class="col-sm-2 col-form-label" for="suffix">後綴</label>
                            <div class="col-sm-3"><input type="text" class="form-control" id="suffix" name="suffix" maxlength="50" value="{{ old('suffix') }}" placeholder="例：組"></div>
                        </div>
                        <div class="form-group row">
                            <label class="col-sm-2 col-form-label" for="numeral">編號格式</label>
                            <div class="col-sm-3">
                                <select class="form-control" id="numeral" name="numeral">
                                    <option value="arabic" @selected(old('numeral', 'arabic') === 'arabic')>阿拉伯數字（1, 2, 3）</option>
                                    <option value="chinese" @selected(old('numeral') === 'chinese')>中文數字（一、二、三）</option>
                                </select>
                            </div>
                            <label class="col-sm-2 col-form-label" for="pad">補零位數</label>
                            <div class="col-sm-3">
                                <select class="form-control" id="pad" name="pad">
                                    @foreach([0 => '不補零', 2 => '2 位（01）', 3 => '3 位（001）', 4 => '4 位（0001）'] as $value => $label)
                                        <option value="{{ $value }}" @selected((int) old('pad', 2) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-sm-2 col-form-label" for="start">起始號碼</label>
                            <div class="col-sm-3"><input type="number" class="form-control" id="start" name="start" min="1" max="9999" value="{{ old('start', $groups->count() + 1) }}"></div>
                        </div>
                    </div>

                    <div id="custom-fields">
                        <div class="form-group row">
                            <label class="col-sm-2 col-form-label" for="names">組別名單</label>
                            <div class="col-sm-6">
                                <textarea class="form-control" id="names" name="names" rows="8" placeholder="一行一組，例如：&#10;慈心組&#10;悲心組">{{ old('names') }}</textarea>
                                <small class="form-text text-muted">名單 <span id="line-count">0</span> 行，將以名單行數為準建立組別；空白行會略過。</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-sm-2 col-form-label" for="category">分類</label>
                        <div class="col-sm-3"><input type="text" class="form-control" id="category" name="category" maxlength="255" value="{{ old('category') }}"></div>
                        <div class="col-sm-7 col-form-label text-muted small">選填，套用到這次建立的所有組別；建立後可在上方各別修改</div>
                    </div>

                    <div class="form-group row">
                        <div class="col-sm-2 col-form-label">預覽</div>
                        <div class="col-sm-10 col-form-label" id="preview"></div>
                    </div>

                    <button type="submit" class="btn btn-success">{{ $isFirst ? '建立組別' : '新增組別' }}</button>
                </form>
            </div>
        </div>
    @endif

    <script>
        function removeGroup(id, alias) {
            if (confirm('確認刪除「' + alias + '」？')) {
                const form = document.getElementById('remove-group-form');
                form.group_id.value = id;
                form.submit();
            }
        }

        (function () {
            const form = document.getElementById('generate-form');
            if (!form) {
                return;
            }
            const $ = (id) => document.getElementById(id);
            const digits = ['零', '一', '二', '三', '四', '五', '六', '七', '八', '九'];
            const units = ['', '十', '百', '千'];

            // 與 ApplicantsGroupController::chineseNumber() 相同的規則
            function chineseNumber(n) {
                const str = String(n);
                let result = '';
                let pendingZero = false;
                for (let i = 0; i < str.length; i++) {
                    const digit = Number(str[i]);
                    if (digit === 0) {
                        pendingZero = true;
                        continue;
                    }
                    if (pendingZero) {
                        result += '零';
                        pendingZero = false;
                    }
                    result += digits[digit] + units[str.length - i - 1];
                }
                return str.length === 2 && result.startsWith('一十') ? result.slice(1) : result;
            }

            function customLines() {
                return $('names').value.split(/\r?\n/).map((line) => line.trim()).filter((line) => line);
            }

            function aliases() {
                if (form.mode.value === 'custom') {
                    return customLines();
                }
                const count = parseInt($('count').value, 10) || 0;
                const start = parseInt($('start').value, 10) || 1;
                const pad = parseInt($('pad').value, 10) || 0;
                const list = [];
                for (let i = 0; i < Math.min(count, 200); i++) {
                    const n = start + i;
                    const number = $('numeral').value === 'chinese' ? chineseNumber(n) : String(n).padStart(pad, '0');
                    list.push($('prefix').value.trim() + number + $('suffix').value.trim());
                }
                return list;
            }

            function render() {
                const custom = form.mode.value === 'custom';
                $('simple-fields').style.display = custom ? 'none' : '';
                $('custom-fields').style.display = custom ? '' : 'none';
                $('pad').disabled = $('numeral').value === 'chinese';
                $('line-count').textContent = customLines().length;

                const list = aliases();
                const shown = list.length > 8 ? [...list.slice(0, 5), '…', ...list.slice(-2)] : list;
                $('preview').textContent = list.length ? shown.join('、') + '（共 ' + list.length + ' 組）' : '（無）';
            }

            form.addEventListener('input', render);
            form.addEventListener('change', render);
            form.addEventListener('submit', function (event) {
                if (form.mode.value !== 'custom') {
                    return;
                }
                const lines = customLines().length;
                const count = parseInt($('count').value, 10) || 0;
                if (lines !== count && !confirm('名單有 ' + lines + ' 行，與組數 ' + count + ' 不同，將以名單行數建立 ' + lines + ' 組，確定嗎？')) {
                    event.preventDefault();
                }
            });
            render();
        })();
    </script>
@endsection
