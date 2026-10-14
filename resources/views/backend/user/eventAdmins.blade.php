@extends('backend.master')
@section('content')
    <h2 class="d-inline-block">event_admin 管理</h2>
    <a href="{{ route('campManagement') }}" class="btn btn-outline-secondary btn-sm ml-3 align-top">回營隊列表</a>
    <p class="text-muted">event_admin 可以建立、修改所指定類型的營隊，並管理這些營隊的梯次與組織。只需勾選主營隊類型，對應的義工營（例如 ycamp → yvcamp）會自動包含。</p>

    @if(\Session::has('error'))
        <div class='alert alert-danger' role='alert'>{{ \Session::get('error') }}</div>
    @endif
    @if(\Session::has('message'))
        <div class='alert alert-success' role='alert'>{{ \Session::get('message') }}</div>
    @endif
    @if($errors->any())
        <div class='alert alert-danger' role='alert'>{{ $errors->first() }}</div>
    @endif

    {{-- 新增 / 修改 event_admin --}}
    <div class="card p-3 mb-4">
        <h4 class="card-title">新增或修改 event_admin</h4>
        <form method="post" action="{{ route('saveEventAdmin') }}">
            @csrf
            <div class="row form-group">
                <label class="col-md-2 control-label">使用者</label>
                <div class="col-md-6">
                    <input type="text" name="user" id="input_event_admin_user" class="form-control" value="{{ old('user') }}" placeholder="Email 或使用者 ID" required>
                </div>
            </div>
            <div class="row form-group">
                <label class="col-md-2 control-label">可管理的營隊類型</label>
                <div class="col-md-8">
                    @foreach($campTables as $table)
                        <div class="form-check form-check-inline">
                            <input type="checkbox" name="camp_tables[]" value="{{ $table }}" id="table_{{ $table }}" class="form-check-input camp-table-checkbox"
                                @checked(in_array($table, old('camp_tables', [])))>
                            <label class="form-check-label" for="table_{{ $table }}">{{ $table }}</label>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 offset-md-2">
                    <button class="btn btn-primary">儲存</button>
                    <small class="form-text text-muted">已是 event_admin 的使用者，會以這次勾選的類型取代原本的設定。</small>
                </div>
            </div>
        </form>
    </div>

    {{-- 目前的 event_admin --}}
    <div class="card p-3">
        <h4 class="card-title">目前的 event_admin（{{ $eventAdmins->count() }} 位）</h4>
        @if($eventAdmins->isEmpty())
            <p class="text-muted mb-0">目前沒有 event_admin。</p>
        @else
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th style="width: 6em;">ID</th>
                        <th>姓名</th>
                        <th>Email</th>
                        <th>可管理的營隊類型</th>
                        <th style="width: 10em;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($eventAdmins as $eventAdmin)
                        @php $tables = ($scopes[$eventAdmin->id] ?? collect())->pluck('camp_table'); @endphp
                        <tr>
                            <td>{{ $eventAdmin->id }}</td>
                            <td>{{ $eventAdmin->name }}</td>
                            <td>{{ $eventAdmin->email }}</td>
                            <td>{{ $tables->isEmpty() ? '（無）' : $tables->implode('、') }}</td>
                            <td class="text-nowrap">
                                <button type="button" class="btn btn-outline-primary btn-sm"
                                    onclick="editEventAdmin(@js($eventAdmin->email), @js($tables->values()))">修改</button>
                                <form method="post" action="{{ route('removeEventAdmin') }}" class="d-inline"
                                    onsubmit="return confirm(@js('確定要移除 ' . $eventAdmin->name . ' 的 event_admin 身分？'));">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $eventAdmin->id }}">
                                    <button class="btn btn-outline-danger btn-sm">移除</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <script>
        // 把該 event_admin 的資料帶入上方表單
        function editEventAdmin(email, tables) {
            document.getElementById('input_event_admin_user').value = email;
            document.querySelectorAll('.camp-table-checkbox').forEach(function (checkbox) {
                checkbox.checked = tables.includes(checkbox.value);
            });
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    </script>
@endsection
