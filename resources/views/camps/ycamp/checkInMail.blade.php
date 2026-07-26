<style>
    u{
        color: red;
    }
</style>
<h2 class="center">{{ $applicant->batch->camp->fullName }}<br>【行前通知單】</h2>
<p class="card-text">親愛的 {{ $applicant->name }} 同學您好：</p>
<p class="card-text text-indent">歡迎您參加「{{ $applicant->batch->camp->fullName }}」，很高興能與您一同展開這段學習、交流與成長的旅程。期待在營隊中與您相遇，共享這場豐富而溫暖的心靈饗宴。底下是出發前想再提醒您的重要訊息。</p>
<p class="card-text text-indent">
您的報名序號：{{ $applicant->id }}<br>
您的錄取編號：{{ $applicant->group }}{{ $applicant->number }}<br>
營隊日期：{{ $applicant->batch->batch_start }}({{ $applicant->batch->batch_start_weekday }}) ~ {{ $applicant->batch->batch_end }}({{ $applicant->batch->batch_end_weekday }})，共三天兩夜<br>
營隊地點：{{ $applicant->batch->locationName }}({{ $applicant->batch->location }})<br>
</p>
<ul>
    <li><p class="card-text indent"><a href="{{ $content_link_chn }}">錄取/報到通知連結</a></p></li>
    <li><p class="card-text indent"></p>營隊交通資訊、建議攜帶物品及各項注意事項，皆已整理於上述連結中。請您於出發前務必詳讀，讓我們一起做好準備，迎接這段美好的相聚時光！</li>
</ul>
<br>
<p class="card-text text-right">財團法人福智文教基金會 敬啟</p>
<p class="card-text text-right">{{ \Carbon\Carbon::now()->format('Y 年 n 月 j 日') }}</p>
