<style>
    {{-- u{
        color: red;
    } --}}    
    .right{
        text-align: right;
    }
</style>
@php
$today = \Carbon\Carbon::now();
$str1 = '/img/'. $today->year . $applicant->batch->camp->table . 'MailHeader.png';
$str2 = '/img/'. $today->year . $applicant->batch->camp->table . 'MailFooter.png';
$header_path = $message->embed(public_path() . $str1);
$footer_path = $message->embed(public_path() . $str2);
@endphp
<body style="font-size:16px;">
<table role="presentation"  cellpadding="0" cellspacing="0" border="0" align="center" width="100%">
    <tr><td><img width="100%" src="{{ $header_path }}" /></td></tr>
    <tr><td>
        親愛的企業主管您好 :<br><br>
        &emsp;&emsp;非常感謝您報名福智文教基金會舉辦的{{ $campInfo->fullName }}<br><br>
        @if($mailType == "notAdmitted")
            &emsp;&emsp;感謝您對生命議題的關心與支持，因為舉辦活動場地及報名資格的限制，無法讓所有報名營隊者都能夠參加營隊。<br><br>        
            &emsp;&emsp;福智文教基金會，不定期會舉辦各種大型活動，或各種議題的社區活動，竭誠歡迎您的參與。藉由這些活動，期盼您能夠更深入了解福智文教基金會及其所屬單位回饋社會的熱情與努力。<br><br>
        @elseif($mailType == "thankYou")
            &emsp;&emsp;今年雖然無法與您在營隊中見面，但福智文教基金會，不定期舉辦各種大型活動，或各種議題的社區活動，竭誠歡迎您的參與。藉由這些活動，期盼您能夠更深入了解福智文教基金會及其所屬單位回饋社會的熱情與努力。<br><br>
        @endif
        &emsp;&emsp;若您對營隊有任何問題，歡迎您透過以下的方式聯絡本基金會，我們將盡速為您服務。感謝您的支持！<br><br>
        財團法人福智文教基金會<br>
        {{ \Carbon\Carbon::now()->format('Y 年 n 月 j 日') }}<br><br>
        {!! nl2br(e(str_replace('\n', "\n", $applicant->batch->contact_card))) !!}
        福智文教基金會會員中心：<a href="https://circles.bwfoce.org" target="_blank">https://circles.bwfoce.org</a>
    </td></tr>   
    <tr><td><br><br><img width="100%" height="20%" src="{{ $footer_path }}" /></td></tr>
</table>
</body>

