
請記下您的<span class="text-danger font-weight-bold">《 報名序號：{{ $applicant->id }} 》</span>作為日後查詢使用。
如需修改報名表内容，可至 https://bwcamp.bwfoce.org/camp/{{ $applicant->batch_id }}/query 頁面修改。
<br>
&emsp;&emsp;我們將於 <u>{{ $camp_data->admission_announcing_date }} ({{ $camp_data->admission_announcing_date_weekday }})</u> 寄發錄取與否通知信件至您的電子信箱，或您可至活動網站 <a href="{{ $camp_data->site_url }}">{{ $camp_data->site_url }}</a> 查詢。
<br><br>
<p>
財團法人福智文教基金會<br>
{{ \Carbon\Carbon::now()->format('Y 年 n 月 j 日') }}<br>
--<br>
{!! nl2br(e(str_replace('\n', "\n", $applicant->batch->contact_card))) !!}
</p>
