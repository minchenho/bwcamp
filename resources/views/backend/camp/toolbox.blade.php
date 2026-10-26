@extends('backend.master')
@section('content')
    <style>
        .toolbox-card { transition: box-shadow .15s; }
        .toolbox-card:hover { box-shadow: 0 .25rem .75rem rgba(0, 0, 0, .1); }
        .toolbox-card .stretched-link { color: inherit; text-decoration: none; }
        .toolbox-card.disabled { opacity: .6; }
    </style>

    @include('backend.camp._toolboxNav')
    <h2>{{ $camp->fullName }}</h2>
    <p class="text-muted">
        {{ $camp->abbreviation }}・{{ $camp->table }}
        @if($camp->registration_start || $camp->registration_end)
            ・報名 {{ $camp->registration_start }} ～ {{ $camp->registration_end }}
        @endif
    </p>

    @if(\Session::has('error'))
        <div class='alert alert-danger' role='alert'>{{ \Session::get('error') }}</div>
    @endif
    @if(\Session::has('message'))
        <div class='alert alert-success' role='alert'>{{ \Session::get('message') }}</div>
    @endif

    @php
        $tools = [
            ['title' => '營隊設定', 'desc' => '名稱、類型、報名與錄取日期、地區', 'route' => route('showModifyCamp', $camp->id)],
            ['title' => '梯次', 'desc' => $counts['batches'] . ' 個梯次，日期、地點、組數', 'route' => route('showBatch', $camp->id)],
        ];
        if (!$camp->isVcamp) {
            $tools[] = ['title' => '組織職務', 'desc' => $counts['orgs'] . ' 個職務，權限設定與複製', 'route' => route('showOrgs', $camp->id)];
            $tools[] = ['title' => '組別編排', 'desc' => $counts['groups'] . ' 個學員組別（建置中）', 'route' => null];
            if (auth()->user()->canAssignOrgsDirectly()) {
                $tools[] = ['title' => '直接指定職務', 'desc' => '不經報名流程，直接指定使用者職務（限 superuser）', 'route' => route('showAssignUsers', $camp->id)];
            }
        }
        $tools[] = ['title' => '營隊後台', 'desc' => '名單、錄取、報到等日常作業', 'route' => route('campIndex', $camp->id)];
        if ($relatedCamp) {
            $tools[] = ['title' => $camp->isVcamp ? '對應學員營隊' : '對應義工營隊', 'desc' => $relatedCamp->abbreviation . ' 的營隊管理', 'route' => route('showCampToolbox', $relatedCamp->id)];
        }
    @endphp

    <div class="row">
        @foreach($tools as $tool)
            <div class="col-sm-6 col-lg-4 mb-3">
                <div class="card h-100 toolbox-card @if(!$tool['route']) disabled @endif">
                    <div class="card-body">
                        <h5 class="card-title">
                            @if($tool['route'])
                                <a href="{{ $tool['route'] }}" class="stretched-link">{{ $tool['title'] }}</a>
                            @else
                                {{ $tool['title'] }}
                            @endif
                        </h5>
                        <p class="card-text text-muted small">{{ $tool['desc'] }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
