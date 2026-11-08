{{-- 關懷記錄的標籤徽章；$contactlog 需有 tags 關聯 --}}
@foreach($contactlog->tags as $tag)
    <span class="badge" style="{{ $tag->badge_style }}" @if($tag->notes) title="{{ $tag->notes }}" @endif>{{ $tag->tag }}</span>
@endforeach
