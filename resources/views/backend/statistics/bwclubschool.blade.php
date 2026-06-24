@extends('backend.master')
@section('content')
    <div style="margin-bottom: 15px; padding-left: 10px; border-left: 5px solid #2563eb;">
        <h2 style="font-weight: 700; color: #0f172a; margin: 0; font-size: 26px; line-height: 1.2;">
            {{ $campFullData->abbreviation }}
        </h2>
        <div style="color: #475569; font-size: 16px; margin-top: 4px; font-weight: 600;">
            {{ $title1 }}
        </div>
    </div>

    <div class="panel panel-default" style="border: 1px solid #cbd5e1; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); overflow: hidden; margin-bottom: 10px;">
        <table class="table table-hover" style="margin-bottom: 0; background-color: #fff; border-collapse: separate;">
            <thead>
                <tr style="background-color: #f1f5f9; color: #1e293b; font-weight: 700; font-size: 16px;">
                    <th class="text-center" style="width: 18%; padding: 10px 8px; border-bottom: 2px solid #94a3b8; border-top: none;">{{ $title2 }}</th>
                    <th class="text-left" style="width: 50%; padding: 10px 12px; border-bottom: 2px solid #94a3b8; border-top: none;">學校全名</th>
                    <th class="text-center" style="width: 14%; padding: 10px 8px; border-bottom: 2px solid #94a3b8; border-top: none;">報名人數</th>
                    <th class="text-center" style="width: 18%; padding: 10px 8px; border-bottom: 2px solid #94a3b8; border-top: none;">小計</th>
                </tr>
            </thead>
            <tbody style="font-size: 16px; color: #1e293b;">
                @php
                    $all = 0;    
                @endphp
                @foreach ($groups as $groupname => $schools)
                    @foreach ($schools as $school)
                        @if($loop->first)
                            <tr style="transition: background-color 0.15s ease;">
                                <td rowspan="{{ count($schools) }}" style="vertical-align: middle !important; font-weight: 700; background-color: #f8fafc; color: #1d4ed8; font-size: 16px; border-right: 1px solid #cbd5e1; border-bottom: 1px solid #cbd5e1; padding: 10px 8px;">
                                    {{ $groupname }}
                                </td>
                                <td class="text-left" style="padding: 10px 12px; vertical-align: middle; border-bottom: 1px solid #e2e8f0;">
                                    <div style="font-weight: 700; color: #0f172a; font-size: 16px;">{{ $school ?? "" }}</div>
                                    @if(isset($school_names[$school]))
                                        <div style="color: #475569; font-size: 13px; margin-top: 3px; line-height: 1.4; font-weight: 500;">
                                            <span style="background-color: #f1f5f9; padding: 1px 5px; border-radius: 4px; display: inline-block; border: 1px solid #e2e8f0;">
                                                <i class="fa fa-tags" style="margin-right: 4px; color: #64748b;"></i>{{ implode(", ", $school_names[$school]) }}
                                            </span>
                                        </div>
                                    @endif
                                </td>
                                <td class="text-center" style="vertical-align: middle; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #334155; padding: 10px 8px;">
                                    {{ $totals[$school] }}
                                </td>  
                                <td rowspan="{{ count($schools) }}" style="vertical-align: middle !important; background-color: #fdfdfd; border-left: 1px solid #cbd5e1; border-bottom: 1px solid #cbd5e1; padding: 10px 8px;" class="text-center">
                                    <span style="background-color: #dbeafe; color: #1e40af; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 15px; display: inline-block; border: 1px solid #bfdbfe;">
                                        {{ $totals[$groupname] }} 人
                                    </span>
                                </td>   
                            </tr>
                        @else
                            <tr style="transition: background-color 0.15s ease;">
                                <td class="text-left" style="padding: 10px 12px; vertical-align: middle; border-bottom: 1px solid #e2e8f0;">
                                    <div style="font-weight: 700; color: #0f172a; font-size: 16px;">{{ $school ?? "" }}</div>
                                    @if(isset($school_names[$school]))
                                        <div style="color: #475569; font-size: 13px; margin-top: 3px; line-height: 1.4; font-weight: 500;">
                                            <span style="background-color: #f1f5f9; padding: 1px 5px; border-radius: 4px; display: inline-block; border: 1px solid #e2e8f0;">
                                                <i class="fa fa-tags" style="margin-right: 4px; color: #64748b;"></i>{{ implode(", ", $school_names[$school]) }}
                                            </span>
                                        </div>
                                    @endif
                                </td>
                                <td class="text-center" style="vertical-align: middle; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #334155; padding: 10px 8px;">
                                    {{ $totals[$school] }}
                                </td>   
                            </tr>    
                        @endif
                    @endforeach
                    @php
                        $all += $totals[$groupname];    
                    @endphp
                @endforeach
            </tbody>
            
            <tfoot>
                <tr style="background-color: #1e293b; color: #fff; font-weight: 700;">
                    <td class="text-center" style="padding: 14px 8px; border: none; font-size: 16px; letter-spacing: 2px;">總計</td>
                    <td class="text-center" colspan="3" style="padding: 14px 8px; font-size: 20px; border: none; letter-spacing: 1px;">
                        {{ $all }} <span style="font-size: 14px; font-weight: 400; color: #cbd5e1; margin-left: 4px;">總報名人數</span>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <style>
        .table-hover > tbody > tr:hover {
            background-color: #f1f5f9 !important; /* 稍微加深 Hover 顏色，讓放大後的橫列更清晰 */
        }
    </style>
@endsection