@php
    $cancellation_add1_with_weekday = \Carbon\Carbon::parse($camp_info->cancellation_deadline)->addDays(1)->isoFormat('YYYY-MM-DD(dd)');
@endphp
@if(isset($camp_info->variant) && str_contains($camp_info->variant,'mabu'))
請記下您的<b><span style="color: #DC3545;">《 報名序號：{{ $applicant->id }} 》</span></b>作為日後查詢使用。
<br>
錄取方式​​​：經審核報名資格，將於七日內以email 通知錄取與繳費資訊。請於期限內完成繳費，即正式完成報名！<br>
<p><b>取消參加退費原則</b></p>
<p>{{ $camp_info->cancellation_deadline }}({{ $camp_info->cancellation_deadline_weekday }})(含)前可全額退費(需扣除5%手續費)；<br>
{{ $cancellation_add1_with_weekday }}(含)以後恕不退費。</p>
<br>
@elseif(isset($camp_info->variant) && str_contains($camp_info->variant,'taichung'))
請記下您的<span class="text-danger font-weight-bold">《 報名序號：{{ $applicant->id }} 》</span>作為日後查詢使用。
如需修改報名表内容，可至 https://bwcamp.bwfoce.org/camp/{{ $applicant->batch_id }}/query 頁面修改。
<br>
&emsp;&emsp;我們將於 <u>{{ $camp_data->admission_announcing_date }} ({{ $camp_data->admission_announcing_date_weekday }})</u> 寄發錄取與否通知信件至您的電子信箱，或您可至活動網站 <a href="{{ $camp_data->site_url }}">{{ $camp_data->site_url }}</a> 查詢。
<br><br>
@endif
<p>
財團法人福智文教基金會<br>
{{ \Carbon\Carbon::now()->format('Y 年 n 月 j 日') }}<br>
--<br>
{!! nl2br(e(str_replace('\n', "\n", $applicant->batch->contact_card))) !!}
</p>
