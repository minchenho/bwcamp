@extends('backend.master')
@section('content')
    @include('backend.in_camp.partials.breadcrumb', ['crumbCamp' => $camp, 'crumbLevel' => 'campLogs'])
    <h4>全營關懷記錄</h4>
    <p class="text-muted">{{ $camp->abbreviation }}・只列出您有權限查看的學員的記錄</p>

    @if($tags->isNotEmpty())
        <form action="{{ route('showCampContactLogs', $camp->id) }}" method="GET" class="mb-3">
            <b>依標籤篩選</b><small class="text-muted">（含任一所選標籤）</small><br>
            @foreach($tags as $tag)
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="tag_ids[]" id="filter-tag-{{ $tag->id }}" value="{{ $tag->id }}" @checked($tagIds->contains($tag->id))>
                    <label class="form-check-label" for="filter-tag-{{ $tag->id }}" @if($tag->notes) title="{{ $tag->notes }}" @endif><span class="badge" style="{{ $tag->badge_style }}">{{ $tag->tag }}</span></label>
                </div>
            @endforeach
            <button type="submit" class="btn btn-primary btn-sm">篩選</button>
            @if($tagIds->isNotEmpty())
                <a href="{{ route('showCampContactLogs', $camp->id) }}" class="btn btn-outline-secondary btn-sm">清除</a>
            @endif
        </form>
    @else
        @include('backend.in_camp.partials.noContactLogTags', ['tagCamp' => $camp])
    @endif

    <p>共 {{ $contactlogs->total() }} 筆</p>
    <table class="table table-bordered table-sm">
        <tr>
            <th class="text-nowrap">學員</th>
            <th>內容</th>
            <th>標籤</th>
            <th class="text-nowrap">記錄者</th>
            <th class="text-nowrap">記錄時間</th>
        </tr>
        @forelse($contactlogs as $contactlog)
            <tr>
                <td class="text-nowrap">
                    <a href="{{ route('showContactLogs', [$camp->id, $contactlog->applicant_id]) }}?from={{ \App\Services\InCampBreadcrumb::FROM_CAMP_LOGS }}">{{ $contactlog->applicant?->name }}</a>
                    @if($contactlog->applicant?->group)
                        <br><small class="text-muted">{{ $contactlog->applicant->group }}</small>
                    @endif
                </td>
                <td>{{ $contactlog->notes }}</td>
                <td>@include('backend.in_camp.partials.contactLogTags')</td>
                <td class="text-nowrap">{{ $contactlog->takenby_name }}</td>
                <td class="text-nowrap">{{ $contactlog->created_at }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted">沒有符合的關懷記錄</td></tr>
        @endforelse
    </table>
    {{ $contactlogs->links() }}
@endsection
