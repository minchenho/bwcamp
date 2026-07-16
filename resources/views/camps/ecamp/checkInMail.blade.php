<style>
    .right{
        text-align: right;
    }
</style>
@php
$today = \Carbon\Carbon::now();
$str1 = '/img/'. $today->year . $applicant->batch->camp->table . 'MailHeader.png';
$str2 = '/img/'. $today->year . $applicant->batch->camp->table . 'MailFooter.png';
$str3 = '/img/'. $today->year . $applicant->batch->camp->table . 'Map.png';
$header_path = $message->embed(public_path() . $str1);
$footer_path = $message->embed(public_path() . $str2);
$map_path = $message->embed(public_path() . $str3);
@endphp
<body style="font-size:16px;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" width="100%">
    <tr><td><img width="100%" src="{{ $header_path }}" /></td></tr>
    <tr><td>
        <table width="80%" border="0" cellpadding="0" cellspacing="0" align="center">
            <tr><td>
            <h2 align="center">{{ $applicant->batch->camp->fullName }}<br> 報&nbsp;到&nbsp;通&nbsp;知&nbsp;單</h2>
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
            @if(str_contains($applicant->batch->name, "北區"))
                親愛的企業主管您好 : <br><br>
                感謝您報名參加「{{ $applicant->batch->camp->fullName }}」，期待您的蒞臨，並能有豐盛的收穫！<br><br>
                為使三天的營隊能順利進行，請詳閱以下的「注意事項」。<br>
                並請於報到時，攜帶電子郵件附件內的QR&nbsp;Code辦理報到手續。<br><br>
                <ul>
                    <li>營隊期間：{{ $applicant->batch->batch_start }}&nbsp;({{ $applicant->batch->batch_start_weekday }})&nbsp;至&nbsp;{{ $applicant->batch->batch_end }}&nbsp;({{ $applicant->batch->batch_end_weekday }})。<br>
                    </li>
                    <li>報到時間：{{ $applicant->batch->batch_start }}&nbsp;({{ $applicant->batch->batch_start_weekday }})&nbsp;上午09:30~10:30。<br>
                    </li>
                    <li>報到地點：{{ $applicant->batch->locationName }}&nbsp;愛園大樓前({{ $applicant->batch->location }})<br>
                    </li>
                    <li>交通資訊：<br>
                        【接駁服務】<br>
                        {{ $applicant->batch->batch_start->format('n/j') }}&nbsp;報到當天，基金會在以下地點，提供交通接駁服務；現場將有穿著黃色背心的義工協助引導。<br>
                        <ol>
                        <li>&nbsp;外縣市學員/電訪回覆台北車站接駁：<br>
                        <a style="color: red;">09:10~09:40</a>&nbsp;於<a style="color: red;"><u>台鐵台北車站一樓&nbsp;南一門</u></a><br>
                        (逾09:40，請自行搭乘計程車前往。)<br>
                        </li>
                        <li>&nbsp;台北市/新北市學員或電訪回覆一般捷運接駁：<br>
                        <a style="color: red;">09:10~10:15</a>&nbsp;於<a style="color: red;"><u>台北捷運淡水信義線(紅線)&nbsp;復興崗站1號出口</u></a><br>
                        (逾10:15，請自行搭乘計程車前往。)<br>
                        </li>
                        </ol>
                        【自行前往】<br>
                        請導航「{{ $applicant->batch->locationName }}」({{ $applicant->batch->location }}) <br>
                        <a href="https://maps.app.goo.gl/cpuCXGWN7TKGwArf7" target="_blank">https://maps.app.goo.gl/cpuCXGWN7TKGwArf7</a><br><br>
                    </li>
                    <li>建議攜帶物品：(謹列出參加活動所需攜帶物品，方便您準備行李。)<br>
                    <ol>
                        <li>多套換洗衣物&nbsp;(洗衣不方便)、衣物袋&nbsp;(穿過衣物裝袋) </li>
                        <li>個人常用藥物 </li>
                        <li>個人盥洗用品&nbsp;(含刮鬍刀、指甲刀等)</li>
                        <li>個人用品&nbsp;(以防他人打鼾耳塞、眼罩、口罩、手帕等)</li>
                        <li>拖鞋、衣架</li>
                        <li>輕薄外套&nbsp;(預防上課地點冷氣過冷)</li>
                        <li>隨身背包&nbsp;(教材約A4大小)、文具用品、摺疊傘、遮陽帽</li>
                        <li>環保水杯&nbsp;(壺)</li>
                        <li>身份證、健保卡&nbsp;(就醫時需要)</li>
                        <li>因為校區為山坡地形，請盡量穿著舒適好走的鞋子，方便移動。</li>
                        <li>本營隊會提供包含枕頭、枕頭套、涼被x2、巧拼墊等寢具相關用品。<br>服務台也備有吹風機，需要時可以直接借用，但因數量有限，使用完請盡速歸還，吹風機亦可自備。</li>
                    </ol>
                    </li><br>
                    <li>注意事項：<br>
                    <ol>
                        <li>本次營隊報名踴躍，因場地空間有限，若您無法全程參加，請告知關懷員，非常感恩您！</li>
                        <li>營隊期間，亦安排晚間學習課程，為達到研習效果，請勿外出及外宿。</li>
                        <li>會場停車位有限，為響應節能減碳，懇請多加利用本會提供之交通接駁服務。</li>
                        <li>{{ $applicant->batch->batch_end->format('n/j') }}&nbsp;課程預定於&nbsp;16:50&nbsp;結束，如需訂購回程車票，請考慮&nbsp;18:30&nbsp;以後之班次。</li>
                    </ol>
                    </li>
                </ul>
                <br>
                若有任何問題，歡迎與關懷員們聯絡。<br>
                或來電本會&nbsp;(02)7751-6799&nbsp;分機&nbsp;611203&nbsp;企業營報名報到組。<br>
            @elseif(str_contains($applicant->batch->name, "南區"))
                親愛的企業主管您好 : <br><br>
                &emsp;&emsp;歡迎您參加「{{ $applicant->batch->camp->fullName }}」，我們誠摯期待您的到來，希望您能獲得豐盛的收穫。〈<u>請於報到時攜帶電子郵件中，附件內含之QR&nbsp;Code報到。</u>〉<br><br>
                &emsp;&emsp;為使這三天研習進行順利，請詳閱下列須知。 <br><br>
                一、研習日期：{{ $applicant->batch->batch_start }}&nbsp;({{ $applicant->batch->batch_start_weekday }})&nbsp;至&nbsp;{{ $applicant->batch->batch_end }}&nbsp;({{ $applicant->batch->batch_end_weekday }})。<br>
                二、報到時間：{{ $applicant->batch->batch_start }}&nbsp;({{ $applicant->batch->batch_start_weekday }})&nbsp;<a style="color: red;">09:30~10:30。</a><br>
                三、報到地點：{{ $applicant->batch->locationName }}&nbsp;學生餐廳及宿舍門前〈{{ $applicant->batch->location }}〉。<br>
                四、交通方式：<br>
                <ol>
                <li>本基金會將於&nbsp;{{ $applicant->batch->batch_start->format('n/j') }}&nbsp;上午&nbsp;09:10~10:10&nbsp;在屏東火車站出口處大廳提供交通接駁服務。現場有穿黃色背心義工協助引導。逾&nbsp;10:10&nbsp;抵達者請自行搭計程車前往屏東大學<a style="color: blue;">民生校區</a>〈不是屏商校區〉。</li>
                <li>自行前往者請導航：&nbsp;{{ $applicant->batch->locationName }}〈{{ $applicant->batch->location }}〉。</li>
                <li>開車自往者請將<a style="color: blue;">報到通知單</a>印出放在副駕駛座前擋風玻璃下方，以利進入校園。</li>
                </ol><br>
                <u>以下謹列出參加此次活動建議攜帶物品明細，方便您準備行李：</u><br>
                <ol>
                    <li>多套換洗衣物(洗衣不方便)、衣物袋(裝使用過之衣物)。</li>
                    <li>毛巾、牙膏、牙刷、香皂、洗髮精、拖鞋、衣架、輕薄外套(防上課地點冷氣過冷)。</li>
                    <li>隨身背包(教材約A4大小)、文具用品、環保水杯(壺)、摺疊傘、遮陽帽。</li>
                    <li>刮鬍刀、指甲刀、耳塞(以防他人打鼾)、眼罩、口罩、手帕。</li>
                    <li>身份證、健保卡(生病時就醫用)。</li>
                    <li>個人常用藥物。</li>
                    <li>屏東太陽熱情，建議穿著舒適、端莊的輕便衣物。課堂期間請勿穿著背心、細肩帶或過短的下著，感謝您的配合。</li>
                    <li>本營隊會提供包含枕頭x1、涼被x2、舒美墊x3(60cm x 60cm)等寢具。</li>
                    <li>本營隊使用的寢室皆為硬板床，可依自己的需求，自行攜帶軟墊。</li>
                    <li>每樓層服務台備有2-3支吹風機，需要時可以直接借用，但因數量有限，請勿帶進寢室使用，吹風機亦可自備。</li>
                </ol><br>
                注意事項：<br>
                <ol>
                    <li>如有發燒及呼吸道症狀(額溫>=37.5度C)，為維護個人及他人的健康安全，敬請勿參加本次活動，並通知關懷員。</li>
                    <li>若您目前正值懷孕期間，因本營隊每日課程長達十多個小時，整體節奏較為緊湊，且需配合團體住宿。提醍參加前務必再評估目前的身體狀況後參加。</li>
                    <li>因研習課程延伸至晚間，為建立完整的學習環境並達到最佳研習效果，主辦單位統一安排全程住宿，研習期間恕不開放學員私自外出及外宿。若有特殊緊急需求，請務必依規定向關懷員填寫假單申請。</li>
                    <li>會場停車位有限，響應節能減碳，懇請多利用公共交通工具及本會提供的接駁車。</li>
                    <li>{{ $applicant->batch->batch_end->format('n/j') }}&nbsp;課程預定於&nbsp;17:00&nbsp;結束，如需訂購回程車票，請考慮&nbsp;18:10&nbsp;以後之班次。</li>
                </ol><br>
                若有任何問題，歡迎與關懷員聯絡，或來電本會企業課&nbsp;07-2819498&nbsp;企業營報名報到組。<br>
            @elseif(str_contains($applicant->batch->name, "中區"))
                親愛的企業主管您好:：<br><br>
                &emsp;&emsp;歡迎您報名參加「{{ $applicant->batch->camp->fullName }}」，期待您的蒞臨，並能有豐盛的收穫！<br>
                &emsp;&emsp;為使三天的營隊能順利進行，請詳閱以下的「注意事項」。<br>
                &emsp;&emsp;並請於報到時，攜帶電子郵件附件內含的QR&nbsp;Code辦理報到手續。<br><br>
                營隊期間：{{ $applicant->batch->batch_start }}&nbsp;({{ $applicant->batch->batch_start_weekday }})&nbsp;至&nbsp;{{ $applicant->batch->batch_end }}&nbsp;({{ $applicant->batch->batch_end_weekday }})<br>
                報到時間：{{ $applicant->batch->batch_start }}&nbsp;({{ $applicant->batch->batch_start_weekday }})&nbsp;<a style="color: red;">上午09:30~10:30</a><br>
                報到地點：{{ $applicant->batch->locationName }}&nbsp;國秀樓1F&nbsp;({{ $applicant->batch->location }})<br>
                交通：<br>
                1. {{ $applicant->batch->batch_start->format('n/j') }}&nbsp;報到當天，現場將有穿著黃色背心的義工協助引導。<br>
                2. 自行前往：<br>
                請導航：「{{ $applicant->batch->locationName }}」({{ $applicant->batch->location }})<br>
                <a href="https://maps.app.goo.gl/mnyMPKmkZoUS4JBS8" target="_blank">https://maps.app.goo.gl/mnyMPKmkZoUS4JBS8</a><br>
                <img width="100%" src="{{ $map_path }}" /><br>
                <br>
                建議攜帶物品：（謹列出參加活動所需攜帶物品，方便您準備。）<br>
                <ol>
                    <li>個人常用藥物</li>
                    <li>輕薄外套(防上課地點冷氣過冷)</li>
                    <li>隨身背包(教材約A4大小)、文具用品、摺疊傘、遮陽帽</li>
                    <li>環保水杯(壺)、餐具</li>
                    <li>身份證、健保卡(就醫時需要) </li>
                    <li>因為校區廣，請盡量穿著舒適好走的鞋子，方便移動</li>
                </ol>
                注意事項： <br>
                <ol>
                    <li>本次營隊報名踴躍，因場地空間有限，若您無法全程參加，請告知關懷員，非常感恩您！</li>
                    <li>營隊期間，亦安排晚間學習課程，為達到研習效果，請勿外出。</li>
                    <li>{{ $applicant->batch->batch_end->format('n/j') }}&nbsp;課程預定於&nbsp;16:50&nbsp;結束，如需訂購回程車票，請考慮&nbsp;18:30&nbsp;以後之班次。</li>
                </ol>
                <br>
                <br>
                若有任何問題，歡迎與關懷員們聯絡。<br>
                或來電本會<b>(04)3706-9300&nbsp;#621201</b>企業營報名報到
            @endif
            <br><br>
            <a class="right">主辦單位：財團法人福智文教基金會&emsp;敬啟</a><br>
            <a class="right">{{ $today->format('Y 年 n 月 j 日') }}</a>
        </td></tr>
        <tr><td><br><br>
        <img width="100%" height="20%" src="{{ $footer_path }}" />
        </td></tr>
    </td></tr>
</table>
</body>
