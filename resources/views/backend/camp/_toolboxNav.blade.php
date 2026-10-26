{{-- 營隊管理頁面共用的麵包屑；$toolTitle 為目前工具名稱，不傳時代表營隊管理首頁 --}}
<nav aria-label="breadcrumb">
    <ol class="breadcrumb bg-light py-2 mb-3">
        <li class="breadcrumb-item"><a href="{{ route('campManagement') }}">營隊管理</a></li>
        @isset($camp)
            @isset($toolTitle)
                <li class="breadcrumb-item"><a href="{{ route('showCampToolbox', $camp->id) }}">{{ $camp->abbreviation }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $toolTitle }}</li>
            @else
                <li class="breadcrumb-item active" aria-current="page">{{ $camp->abbreviation }}</li>
            @endisset
        @elseif(isset($toolTitle))
            <li class="breadcrumb-item active" aria-current="page">{{ $toolTitle }}</li>
        @endisset
    </ol>
</nav>
