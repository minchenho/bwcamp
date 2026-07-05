<style>
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
<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" width="100%">
    <tr><td><img width="100%" src="{{ $header_path }}" /></td></tr>
    <tr><td>
        <table width="80%" border="0" cellpadding="0" cellspacing="0" align="center">
            <tr><td>
            <h2 class="center">{{ $applicant->batch->camp->fullName }}<br> 錄&nbsp;取&nbsp;通&nbsp;知&nbsp;函</h2>
            </td></tr>
        </table>
        <table width="100%" style="table-layout:fixed; border: 0;">
            <tr><td>
                姓名：{{ $applicant->name }}<br>
                序號：{{ $applicant->id }}<br>
                組別：{{ $applicant->groupRelation?->alias ?? "[異常，請回報主辦單位]" }}<br>
                場次：{{ $applicant->batch->name }}({{ $applicant->batch->locationName }})
            </td></tr>
        </table><br>
    <tr><td>
        親愛的企業主管您好 :<br><br>
        &emsp;&emsp;感謝您報名「{{ $applicant->batch->camp->fullName }}」，誠摯歡迎您參加本研習活動。我們已經為您分編好組別，並安排了充滿熱誠的關懷員提供服務，誠摯的歡迎您來共享這場心靈饗宴，一起翻轉人生，開創無限美好的幸福。<br><br>
        為使研習進行順利，請詳閱下列須知：
        <ol>
            <li>上課時間：
                    {{ $applicant->batch->batch_start }}({{ $applicant->batch->batch_start_weekday }}) 至 {{ $applicant->batch->batch_end }}({{ $applicant->batch->batch_end_weekday }}) </li>
            <li>報到時間：
                    {{ $applicant->batch->batch_start }}({{ $applicant->batch->batch_start_weekday }})&nbsp;<a style="color: red;">
                        @if (str_contains($applicant->batch->name, "北區")) 
                        上午09:30~10:30 
                        @else
                        上午09:30~10:30
                        @endif
                    </a> </li>
            <li>舉辦地點：
                    {{ $applicant->batch->locationName }}&nbsp;({{ $applicant->batch->location }})
                    @if (str_contains($applicant->batch->name, "北區"))<br>
                    <a href='https://maps.app.goo.gl/cpuCXGWN7TKGwArf7'>https://maps.app.goo.gl/cpuCXGWN7TKGwArf7</a>
                    @endif</li>
            <li>
                @if (str_contains($applicant->batch->name, "北區"))
                由於活動現場停車空間有限，誠摯建議優先搭乘大眾運輸工具前往。<br>
                本基金會正以鄰近的大眾運輸中心規劃適當的接駁服務中，細節將儘速公布。<br>
                @else
                交通：（屏東大學民生校區臨近屏東火車站約3公里）<br>
                    <ol>
                        <li>火車：屏東站下車，搭大會接駁車至學校大門口。</li>
                        <li>高鐵：於左營高鐵站下車，轉乘台鐵至屏東站；再乘大會接駁車至學校大門口。</li>
                        <li>自行前往者請導航：{{ $applicant->batch->locationName }}&nbsp;{{ $applicant->batch->location }}。(<a href="https://goo.gl/maps/jbHDZ">https://goo.gl/maps/jbHDZ</a>)</li>
                    </ol>
                    <u>因會場停車位有限，懇請多利用大會接駁車。</u>
                @endif</li>
            @if (str_contains($applicant->batch->name, "北區")) 
            <li>營隊關懷員近日內將透過簡訊及電話與您聯繫。<br>
                如有任何問題，也歡迎主動與關懷員聯絡。<br></li>
            @else
            <li><b>接下來為您安排的關懷員將陸續透過簡訊或電話與您聯繫，<u>請留意陌生訊息及來電</u>，如有任何問題，也歡迎主動與關懷員聯絡。</b><br></li>
            @endif
            <li>{{ $applicant->groupRelation?->alias ?? "[異常，請回報主辦單位]" }}關懷員 :
                <ul>
                    @foreach ($carers as $carer)
                    <li>{{ $carer->name }} {{ $carer->mobile }}</li>
                    @endforeach
                </ul></li>
        </ol><br>
        @if (str_contains($applicant->batch->name, "北區")) 
        敬祝～闔家平安、健康喜樂！<br><br>
        @else
        ※注意事項：<br>
        本次營隊報名踴躍，因場地考量容納有限，若您無法全程參加，請告知關懷員，感謝您的協助！<br><br>
        @endif
        <a class="right">主辦單位：財團法人福智文教基金會&emsp;敬啟</a><br>
        <a class="right">{{ $today->format('Y 年 n 月 j 日') }}</a>
    </td></tr>
    
    <tr><td><br><br>
        <img width="100%" height="20%" src="{{ $footer_path }}" />
    </td></tr>
</table>
</body>
