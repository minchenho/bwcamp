@extends('backend.master')
@section('content')
    <style>
        .card-link{
            color: #3F86FB!important;
        }
        .card-link:hover{
            color: #33B2FF!important;
        }
        /* customize */
        .form-group.required .control-label:after {
            content: "＊";
            color: red;
        }
    </style>
    <h4>學員關懷記錄</h4>
    @if($todo == "add")
    <h5>新增關懷記錄：{{ $applicant->name }}</h5>
    @else
    <h5>修改關懷記錄：{{ $applicant->name }}</h5>
    @endif
    <form action="{{ route("addContactLog", $camp_id) }}" method="POST">
        @csrf
        <br>
        <textarea class=form-control rows=5 required name='notes' id="">{{ old('notes', $contactlog->notes ?? '') }}</textarea>
        @php $checkedTagIds = old('tag_ids', isset($contactlog) ? $contactlog->tags->pluck('id')->all() : []); @endphp
        @if($tags->isNotEmpty())
            <div class="mt-3">
                <b>標籤</b><small class="text-muted">（可複選）</small><br>
                @foreach($tags as $tag)
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="tag_ids[]" id="tag-{{ $tag->id }}" value="{{ $tag->id }}" @checked(in_array($tag->id, $checkedTagIds))>
                        <label class="form-check-label" for="tag-{{ $tag->id }}" @if($tag->notes) title="{{ $tag->notes }}" @endif>{{ $tag->tag }}</label>
                    </div>
                @endforeach
            </div>
        @endif
        <br>
        <input type="hidden" name="applicant_id" value="{{ $applicant->id }}">
        @if($todo == "add")
        <input type="hidden" name="todo" value="add">
        <input type="submit" class="btn btn-success" value="確認送出">
        <a href="{{ route('showContactLogs', [$camp_id, $applicant->id]) }}" class="btn btn-danger">取消新增</a>
        @else
        <input type="hidden" name="todo" value="modify">
        <input type="hidden" name="contactlog_id" value="{{ $contactlog->id }}">
        <input type="submit" class="btn btn-success" value="確認送出">
        <a href="{{ route('showContactLogs', [$camp_id, $applicant->id]) }}" class="btn btn-danger">取消修改</a>
        @endif
    </form>

@endsection