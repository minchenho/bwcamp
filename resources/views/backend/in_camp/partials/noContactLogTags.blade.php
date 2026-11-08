{{-- 營隊類型尚未設定關懷記錄標籤時的提示；能管理此營隊的人另外顯示設定連結。需傳入 $tagCamp --}}
@php $canManageTags = app(\App\Services\CampManageAccessService::class)->canManage(auth()->user(), $tagCamp); @endphp
<div class="alert alert-info {{ $hintClass ?? '' }}">
    此類營隊（{{ $tagCamp->table }}）尚未設定關懷記錄標籤，所以目前無法選擇標籤。
    @if($canManageTags)
        <a href="{{ route('showContactLogTags', $tagCamp->id) }}">前往設定標籤</a>
    @else
        可請營隊管理者在營隊工具箱的「關懷記錄標籤」設定。
    @endif
</div>
