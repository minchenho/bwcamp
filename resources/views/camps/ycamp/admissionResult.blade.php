<style>
    u{
        color: red;
    }
    indent{
        text-indent: 2em;
    }
</style>
@extends('camps.' . $camp_info->table . '.layout')
@section('content')
    @php
        $today = \Carbon\Carbon::now()->midDay();
        $days = $applicant->batch->batch_start->diffInDays($applicant->batch->batch_end) + 1;
        $traffic_confirming_date = $camp_info->admission_confirming_end->addDays(14)->midDay();
        $modifying_deadline = $camp_info->modifying_deadline->endOfDay();
        $applicant->id = $applicant->id ?? $applicant->applicant_id;
    @endphp
    @if(Session::has('error'))
        <div class="alert alert-danger" role="alert">
            {{ Session::get("error") }}
        </div>
    @endif
    <br>
    <div class='page-header form-group'>
        <h4>{{ $camp_info->fullName }}</h4>
    </div>
<!--研習證明可供下載後，就隱藏錄取查詢-->
    <div class="card">
@if($camp_info->certificate_available_date && $today->gte($camp_info->certificate_available_date->startOfDay()))
        <div class="card-header">
            <h5>研習證明下載</h5>
        </div>
        <div class="card-body">
        @if($applicant->is_admitted && !$applicant->deleted_at && $applicant->is_attend)
            <p><a href="https://bwcamp.bwfoce.org/downloads/{{ $camp_info->table }}{{ $camp_info->year }}/{{ $applicant->group }}{{ $applicant->number }}{{ $applicant->id }}.pdf" target="_blank" rel="noopener noreferrer" class="btn btn-light">下載福智文教基金會研習數位證明書</a></p>       
            <p>如下載顯示錯誤，請聯絡您的帶組老師，謝謝！</p>
        @else
            <p>您沒有研習證明可供下載（可能是未登記或未參加完全程）</p>
            <p>如有疑問，請聯絡您的帶組老師，謝謝！</p>
        @endif
@else
        <div class="card-header">
            <h5>錄取查詢</h5>
        </div>
        <div class="card-body">
            @if($applicant->deleted_at)
                <!-----取消報名----->
                <p class="card-text">親愛的 {{ $applicant->name }} 同學您好</p>
                <p class="card-text indent">您已取消報名「{{ $camp_info->fullName }}」，如資料有誤或您想重新報名，請聯絡我們。感謝您！</p>
            @elseif(!$applicant->deleted_at && $applicant->is_admitted)
                <!-----已錄取----->
                <p class="card-text">親愛的 {{ $applicant->name }} 同學您好</p>
                <p class="card-text text-indent">恭喜錄取「{{ $camp_info->fullName }}」！</p>我們竭誠歡迎您的到來！為確保營隊體驗順利，請詳閱下列各項說明。期待與你見面，也祝福你在營隊中收穫滿載。</p>

                <p class="card-text text-indent">
                您的報名序號：{{ $applicant->id }}<br>
                您的錄取編號：{{ $applicant->group }}{{ $applicant->number }}<br>
                營隊期間：{{ $applicant->batch->batch_start }}({{ $applicant->batch->batch_start_weekday }}) ~ {{ $applicant->batch->batch_end }}({{ $applicant->batch->batch_end_weekday }})，共{{ $days }}天<br>
                營隊地點：{{ $applicant->batch->locationName }}({{ $applicant->batch->location }})
                </p><br>

                <h5>錄取/報到通知</h5>
                <div class="ml-2 mb-2">請詳閱<a href="{{ $camp_info->content_link_chn }}">【錄取/交通費通知】</a>，內含報到資訊、必帶物品，及交通資訊等等。</div>
<!--
                <div class="ml-2 mb-2"><a href="{{ url('downloads/ycamp2025/【2025第58屆大專青年生命成長營】錄取通知單.pdf') }}" download class="btn btn-primary" target="_blank" style="margin-top: 10px">下載錄取/報到通知</a></div>
-->
                <br>
                <h5>放棄參加</h5>
                <form class="ml-2 mb-2" action="{{ route('toggleAttend', $batch_id) }}" method="POST" id="attendcancel">
                    @csrf
                    <input type="hidden" name="applicant_id" value="{{ $applicant->id }}">
                    <input type="hidden" name="camp" value="ycamp">
                    @if(!isset($applicant->is_attend) || $applicant->is_attend )
                        <div class="ml-0 mb-2 text-success">您目前的狀態是「參加」。</div>
                        <div class="ml-0 mb-2">如您因故無法參加，請按放棄參加通知我們，謝謝！</div>
                        <div>
                        <input class="btn btn-danger" type="submit" value="放棄參加" id="cancel" name="cancel">
                        </div>
                    @else
                        <div class="ml-0 mb-2 text-danger">您目前的狀態是「放棄參加」。</div>
                        <div class="ml-0 mb-2">如您可以參加了，請按恢復參加，謝謝！</div>
                        <div>
                        <input class="btn btn-success" type="submit" value="恢復參加" id="confirmattend" name="confirmattend">
                        </div>
                    @endif
                </form><br>

                @if(!isset($applicant->is_attend) || $applicant->is_attend)
                    <h5>選擇交通方式</h5>
                @if($today->lte($modifying_deadline))
                    <form class="ml-2 mb-2" action="{{ route('modifyTraffic', $batch_id) }}" method="POST" id="selecttraffic">
                        @csrf
                        <div class="ml-0 mb-2">交通方式預設為自往及自回</div>
                        <div class="ml-0 mb-2">交通資訊請參閱<a href="{{ $camp_info->content_link_chn }}">【錄取/交通費通知】</a>之附件</div>
                        <input type="hidden" name="applicant_id" value="{{ $applicant->id }}">
                        <input type="hidden" name="camp" value="ycamp">
                        <div class='row form-group required'>
                            <label for='inputDepartFrom' class='col-md-2 control-label text-md-right'>去程交通</label>
                            <div class="col-md-4">
                                <select required class='form-control' name='depart_from' id='inputDepartFrom'>
                                    <option value=''>- 請選擇 -</option>
                                    @foreach($fare_depart_from as $key => $value)
                                    <option value='{{ $key }}' >{{ $key }}({{ $value }})</option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback">
                                    請選擇去程交通
                                </div>
                            </div>
                        </div>
                        <div class='row form-group required'>
                            <label for='inputBackTo' class='col-md-2 control-label text-md-right'>回程交通</label>
                            <div class="col-md-4">
                                <select required class='form-control' name='back_to' id='inputBackTo'>
                                    <option value=''>- 請選擇 -</option>
                                    @foreach($fare_back_to as $key => $value)
                                    <option value='{{ $key }}' >{{ $key }}({{ $value }})</option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback">
                                    請選擇回程交通
                                </div>
                            </div>
                        </div>
                        <input class="btn btn-success" type="submit" value="確認修改" id="confirmtraffic" name="confirmtraffic">
                    </form><br>
                @else
                    <div class="ml-0 mb-2">
                        ＊修改交通選項已截止，若有問題，請聯絡各組帶組老師。<br>
                        您的去程交通選項：{{ $applicant->traffic?->depart_from ?? 未選擇 }}<br>
                        您的回程交通選項：{{ $applicant->traffic?->back_to ?? 未選擇 }}
                    </div>
                @endif
                    @if(isset($applicant->traffic) && $applicant->traffic->fare > 0)
                        <form class="ml-2 mb-2" action="{{ route('downloadPaymentForm', $batch_id) }}" method="POST">
                            @csrf
                            <input type="hidden" name="applicant_id" value="{{ $applicant->id }}">
                            <div class="ml-0 mb-2">應交費用：{{ $applicant->traffic->fare }}；已交費用：{{ $applicant->traffic?->sum }}</div>
                            <div><input type="submit" class="btn btn-primary" value="下載繳費單"></div>
                        </form>
                    @endif
                @endif

            @elseif($today->lt($camp_info->rejection_showing_date->startOfDay()))
                <!-----錄取中----->
                <p class="card-text">親愛的 {{ $applicant->name }} 同學您好</p>
                <p class="card-text indent">感謝您報名「{{ $camp_info->fullName }}」，錄取作業正在進行中，請稍後再進行錄取查詢。感謝您的耐心等待！</p>
            @else
                <!-----備取=不錄取----->
                <p class="card-text">親愛的 {{ $applicant->name }} 同學您好</p>
                <p class="card-text indent">非常感謝您報名參加「{{ $camp_info->fullName }}」，由於本活動報名人數踴躍，且場地有限，非常抱歉未能在第一階段錄取您。我們已將您列入優先備取名單，若有遞補機會，基金會將儘速通知您!</p>
                <p class="card-text indent">開學後，各區福青學堂定期都有精彩的課程活動，竭誠歡迎您的參與!也祝福您學業順利，吉祥如意！</p>
                <h5>各區福青學堂資訊</h5>
                <div class="container">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="card-text">
                            台北福青學堂<br>
                            02-2545-3788 #546<br>
                            台北市松山區南京東路四段161號9樓<br>
                            </p>
                            <p class="card-text">
                            桃園福青學堂<br>
                            03-275-6133 #1314<br>
                            桃園市中壢區強國路121號2樓<br>
                            </p>
                            <p class="card-text">
                            新竹福青學堂<br>
                            03-571-0968<br>
                            新竹市東區忠孝路43號2樓<br>
                            </p>
                            <p class="card-text">
                            台中福青學堂<br>
                            04-37069300 #621101<br>
                            台中市西屯區臺灣大道二段669號2樓<br>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p class="card-text">
                            雲嘉福青學堂<br>
                            05-5370133 #125<br>
                            雲林縣斗六市慶生路6號<br>
                            </p>
                            <p class="card-text">
                            台南福青學堂<br>
                            06-289-6558<br>
                            台南市東區崇明路405號4樓<br>
                            </p>
                            <p class="card-text">
                            高雄福青學堂<br>
                            07-974-1170<br>
                            高雄市新興區中正四路53號12樓之7<br>
                            </p>
                            <p class="card-text">
                            花蓮大專班籌備處<br>
                            03-831-6307<br>
                            花蓮市中華路243號2樓<br>
                            </p>
                        </div>
                    </div>
                </div>
            @endif
@endif
            <br>
            <p class="card-text">主辦單位：<br>財團法人福智文教基金會<br>國立雲林科技大學<br>
            {{ \Carbon\Carbon::now()->format('Y 年 n 月 j 日') }}</p>
            <p class="card-text">
            <p class="card-text">Email福智青年：<a href="mailto:youth@blisswisdom.org">youth@blisswisdom.org</a><br>
            留言給福智青年：<a href="https://www.facebook.com/bwyouth" target="_blank" rel="noopener noreferrer">福智青年粉專</a></p>

            --<br>
            洽詢電話：(週一 ~ 週五 上午10時 ~ 下午5時) <br>
            {!! nl2br(e(str_replace('\n', "\n", $applicant->batch->contact_card))) !!}
            </p>
            <input type='button' class='btn btn-warning' value='回上一頁' onclick=self.history.back()>
            <a href="{{ $camp_info->site_url }}" class="btn btn-primary">回營隊首頁</a>
        </div> <!--card-body-->
    </div> <!--card-->

    <script>
        @if(!isset($applicant->is_attend) || $applicant->is_attend)
            let cancel = document.getElementById('cancel');
            cancel.addEventListener('click', function(event) {
                if(confirm('確認放棄參加？')){
                        return true;
                }
                event.preventDefault();
                return false;
            });
        @else
            let confirmattend = document.getElementById('confirmattend');
            confirmattend.addEventListener('click', function(event) {
                console.log("confirmattend");
                if(confirm('確認恢復參加？')){
                        return true;
                }
                event.preventDefault();
                return false;
            });
        @endif
        @if(!isset($applicant->is_attend) || $applicant->is_attend)
            let confirmtraffic = document.getElementById('confirmtraffic');
            confirmtraffic.addEventListener('click', function(event) {
                console.log("confirmtraffic");
                if(confirm('確認修改交通？')){
                        return true;
                }
                event.preventDefault();
                return false;
            });
            {{-- 回填交通選項 --}}
            @if(isset($applicant->traffic))
            (function() {
                let traffic_data = JSON.parse('{!! $applicant->traffic !!}');
                let selects = document.getElementsByTagName('select');
                console.log(traffic_data);
                for (var i = 0; i < selects.length; i++){
                    if(typeof traffic_data[selects[i].name] !== "undefined"){
                        selects[i].value = traffic_data[selects[i].name];
                    }
                }
            })();
            @endif
        @endif
    </script>
@stop
