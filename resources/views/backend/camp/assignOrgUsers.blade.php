@extends('backend.master')
@section('content')
    @php
        $orgLabel = function ($org) {
            $label = $org->isRoot() ? $org->position : $org->section . ' > ' . $org->position;
            return $org->batch ? $label . '（' . $org->batch->name . '）' : $label;
        };
        $orgsById = $orgs->keyBy('id');
    @endphp

    @include('backend.camp._toolboxNav', ['toolTitle' => '直接指定職務'])
    <h2 class="d-inline-block">{{ $camp->abbreviation }} 直接指定使用者職務</h2>
    <a href="{{ route('showOrgs', $camp->id) }}" class="btn btn-outline-secondary btn-sm ml-3 align-top">回組織架構</a>
    <p class="text-muted">此功能僅限 superuser 使用，可不經義工報名流程，直接將使用者指定到本營隊的職務。</p>

    @if(\Session::has('error'))
        <div class='alert alert-danger' role='alert'>{{ \Session::get('error') }}</div>
    @endif
    @if(\Session::has('message'))
        <div class='alert alert-success' role='alert'>{{ \Session::get('message') }}</div>
    @endif
    @if($errors->any())
        <div class='alert alert-danger' role='alert'>{{ $errors->first() }}</div>
    @endif

    {{-- 1. 搜尋使用者 --}}
    <div class="card p-3 mb-4">
        <h4 class="card-title">搜尋使用者</h4>
        <form method="get" action="{{ route('showAssignUsers', $camp->id) }}" class="form-inline">
            <input type="text" name="q" class="form-control mr-2" style="min-width: 280px;" value="{{ $keyword }}" placeholder="姓名、Email 或使用者 ID" required>
            <button class="btn btn-primary">搜尋</button>
        </form>

        @if($keyword !== '')
            @if($searchResults->isEmpty())
                <p class="text-muted mt-3 mb-0">找不到符合「{{ $keyword }}」的使用者。</p>
            @else
                <small class="text-muted mt-3">最多顯示 30 筆；可按住 Ctrl / ⌘ 一次選擇多個職務。</small>
                <table class="table table-bordered table-sm mt-2">
                    <thead>
                        <tr>
                            <th style="width: 6em;">ID</th>
                            <th>姓名</th>
                            <th>Email</th>
                            <th>指定職務</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($searchResults as $user)
                            <tr>
                                <td>{{ $user->id }}</td>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    <form method="post" action="{{ route('assignUser', $camp->id) }}" class="d-flex">
                                        @csrf
                                        <input type="hidden" name="user_id" value="{{ $user->id }}">
                                        <select name="org_ids[]" class="form-control form-control-sm mr-2" multiple required size="4">
                                            @foreach($orgs as $org)
                                                <option value="{{ $org->id }}">{{ $orgLabel($org) }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-success btn-sm align-self-start">指定</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endif
    </div>

    {{-- 2. 目前的職務指派 --}}
    <div class="card p-3">
        <h4 class="card-title">本營隊目前的職務指派（{{ $assignments->count() }} 筆）</h4>
        @if($assignments->isEmpty())
            <p class="text-muted mb-0">目前沒有任何使用者被指定職務。</p>
        @else
            <table class="table table-bordered table-sm table-hover">
                <thead>
                    <tr>
                        <th style="width: 6em;">ID</th>
                        <th>姓名</th>
                        <th>Email</th>
                        <th>職務</th>
                        <th style="width: 6em;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($assignments as $assignment)
                        <tr>
                            <td>{{ $assignment->user_id }}</td>
                            <td>{{ $assignment->name }}</td>
                            <td>{{ $assignment->email }}</td>
                            <td>{{ $orgLabel($orgsById[$assignment->org_id]) }}</td>
                            <td>
                                <form method="post" action="{{ route('unassignUser', $camp->id) }}" onsubmit="return confirm(@js('確定要移除 ' . $assignment->name . ' 的這個職務？'));">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $assignment->user_id }}">
                                    <input type="hidden" name="org_id" value="{{ $assignment->org_id }}">
                                    <button class="btn btn-outline-danger btn-sm">移除</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
