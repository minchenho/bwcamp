@extends('backend.master')
@section('content')
    <style>
        .card-link{
            color: #3F86FB!important;
        }
        .card-link:hover{
            color: #33B2FF!important;
        }
    </style>
    <h2>{{ $camp_info->abbreviation }} 交通名單</h2> 
    
    @foreach ($batches as $batch)
        <hr>
        <h4>
            梯次：{{ $batch->name }}
            <a href="{{ route('showTrafficList', $camp_info->id) }}?batch_id={{ $batch->id }}&download=1" class="btn btn-warning float-right">下載車資繳納明細</a>
        </h4>

        @php
            // 從所有參加者中，過濾出當前特定梯次的人
            $batchApplicants = $attendApplicants->where('batch_id', $batch->id);
            
            // 利用 Controller 傳來的閉包，即時計算該梯次的去回程統計
            $current_traffic_depart = $getTrafficStats($batchApplicants, 'depart');
            $current_traffic_return = $getTrafficStats($batchApplicants, 'return');
            
            $count_depart = 0;    
            $count_return = 0;    
        @endphp

        <h5>去程</h5>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>地點</th>
                    <th>人數</th>
                    <th>動作</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($current_traffic_depart as $t)
                    <tr>
                        <td>{{ $t['traffic_depart'] }}</td>
                        <td>{{ $t['count'] }}</td>
                        <td>
                            <a href="{{ route('showTrafficListLoc', $camp_info->id) }}?batch_id={{ $batch->id }}&depart_from={{ urlencode($t['traffic_depart']) }}&download=0" class="btn btn-info">看名單</a>
                            <a href="{{ route('showTrafficListLoc', $camp_info->id) }}?batch_id={{ $batch->id }}&depart_from={{ urlencode($t['traffic_depart']) }}&download=1" class="btn btn-warning">下載名單</a>
                        </td>
                    </tr>
                    @php $count_depart += $t['count']; @endphp
                @endforeach
            </tbody>
        </table>
        <p>共 {{ $count_depart }} 位</p>

        <h5>回程</h5>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>地點</th>
                    <th>人數</th>
                    <th>動作</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($current_traffic_return as $t)
                    <tr>
                        <td>{{ $t['traffic_return'] }}</td>
                        <td>{{ $t['count'] }}</td>
                        <td>
                            <a href="{{ route('showTrafficListLoc', $camp_info->id) }}?batch_id={{ $batch->id }}&back_to={{ urlencode($t['traffic_return']) }}&download=0" class="btn btn-info">看名單</a>
                            <a href="{{ route('showTrafficListLoc', $camp_info->id) }}?batch_id={{ $batch->id }}&back_to={{ urlencode($t['traffic_return']) }}&download=1" class="btn btn-warning">下載名單</a>
                        </td>
                    </tr>
                    @php $count_return += $t['count']; @endphp
                @endforeach
            </tbody>
        </table>
        <p>共 {{ $count_return }} 位</p>
        <br>
    @endforeach
@endsection