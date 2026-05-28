您所填寫的個人資料，僅用於此次大專營的報名及活動聯絡之用。財團法人福智文教基金會將依個人資料保護法及相關法令之規定善盡保密責任。請記下您的<b><span style="color: #DC3545;">《 報名序號：{{ $applicant->id }} 》</span></b>作為日後查詢使用。<br><br>
&emsp;&emsp;我們會寄送錄取通知信件將到您的電子信箱，並提供網路查詢，請留意<a href="{{ $camp_info->site_url }}">大專營活動官網</a>的相關公告。
<br><br>
<p>
財團法人福智文教基金會<br>
{{ \Carbon\Carbon::now()->format('Y 年 n 月 j 日') }}<br>
--<br>
洽詢電話：(週一 ~ 週五 上午10時 ~ 下午5時) <br>
{!! nl2br(e(str_replace('\n', "\n", $applicant->batch->contact_card))) !!}
</p>
