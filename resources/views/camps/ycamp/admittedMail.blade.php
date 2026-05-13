@php
    $today = \Carbon\Carbon::now()->midDay();
    $days = $applicant->batch->batch_start->diffInDays($applicant->batch->batch_end) + 1;
    $traffic_confirming_date = $camp_info->admission_confirming_date->subDays(14); 
@endphp

<body style="font-size:16px;">
<h2 class="center">{{ $applicant->batch->camp->fullName }}<br>【錄取/交通費 通知單】</h2>
<p class="card-text">親愛的 {{ $applicant->name }} 同學您好：</p>
<p class="card-text text-indent">恭喜錄取「{{ $applicant->batch->camp->fullName }}」，我們竭誠歡迎您的到來！為確保營隊體驗順利，請詳閱下列各項說明。期待與你見面，也祝福你在營隊中收穫滿載。</p>
<p class="card-text text-indent">
你的報名序號：{{ $applicant->id }}<br>
你的錄取編號：{{ $applicant->group }}{{ $applicant->number }}<br>
營隊日期：{{ $applicant->batch->batch_start }}({{ $applicant->batch->batch_start_weekday }}) ~ {{ $applicant->batch->batch_end }}({{ $applicant->batch->batch_end_weekday }})，共{{ $days }}天<br>
營隊地點：{{ $applicant->batch->locationName }}({{ $applicant->batch->location }})<br>
</p>
<ul>
    <li><p class="card-text indent">請詳閱<a href="{{ $content_link_chn }}">【錄取/交通費通知】</a>，內含報到資訊、必帶物品，及交通資訊等等。</p></li>
    <li><p class="card-text indent"><span style="color: #DC3545;">請於6月30日前上網</span><a href="https://bwcamp.bwfoce.org/camp/{{ $applicant->batch->id }}/showadmit?sn={{ $applicant->id }}&name={{ $applicant->name }}">回覆交通方式</a>。交通資訊請參閱</p>
    <p>若以上連結無法點選，請複製下方文字後，再由瀏覽器進入頁面做回覆：</p>
    <p>https://bwcamp.bwfoce.org/camp/{{ $applicant->batch->id }}/queryadmit</p>
    </li>
</ul>
<br>
<p>
財團法人福智文教基金會<br>
{{ $today->format('Y 年 n 月 j 日') }}<br>
--<br>
洽詢電話：(週一 ~ 週五 上午10時 ~ 下午5時) <br>
{!! nl2br(e(str_replace('\n', "\n", $applicant->batch->contact_card))) !!}
</p>
</body>