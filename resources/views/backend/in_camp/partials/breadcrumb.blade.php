{{-- 營隊後台麵包屑：@include('backend.in_camp.partials.breadcrumb', ['crumbCamp' => 營隊, 'crumbApplicant' => 學員或 null, 'crumbLevel' => 'info', 'crumbFrom' => 來源]) --}}
<nav aria-label="breadcrumb">
    <ol class="breadcrumb bg-light py-2 mb-3">
        @foreach(\App\Services\InCampBreadcrumb::items($crumbCamp, $crumbApplicant ?? null, $crumbLevel, $crumbFrom ?? null) as [$label, $url])
            @if($url)
                <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
            @else
                <li class="breadcrumb-item active" aria-current="page">{{ $label }}</li>
            @endif
        @endforeach
    </ol>
</nav>
