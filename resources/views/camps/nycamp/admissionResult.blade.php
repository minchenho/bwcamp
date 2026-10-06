<style>
    u { color: red; }
</style>
@extends('camps.nycamp.layout')
@section('content')
    @php
        $today = \Carbon\Carbon::now()->midDay();
        $applicant->id = $applicant->id ?? $applicant->applicant_id;
        
        // 預設找到基準幣別 (is_std = 1)，若無則取第一個幣別，再沒有就預設 NTD
        $defaultCurrency = $camp_info->currencies->firstWhere('pivot.is_std', 1) ?? $camp_info->currencies->first();
        $defaultCode = $defaultCurrency->code ?? 'NTD';
        $defaultSymbol = $defaultCurrency->symbol ?? '$';
        $shuttleBaseFare = collect($fare_depart_from)->first() ?? 0;
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
{{--
    @if($applicant->is_admitted)
        <div class="card">
            <div class="card-header">
                <h2>研習證明下載</h2>
            </div>
            <div class="card-body">
                <a href="https://bwcamp.bwfoce.org/downloads/{{ $camp_info->table }}{{ $camp_info->year }}/{{ $applicant->group }}{{ $applicant->number }}{{ $applicant->applicant_id }}.pdf" target="_blank" rel="noopener noreferrer" class="btn btn-success">下載</a>
            </div>
            <div class="card-body">
                如下載顯示錯誤，請聯絡您的帶組老師，謝謝！
            </div>
        </div>
        <br>
    @endif
--}}
    <div class="card">
        <!-- 💡 動態標題列： Radio Button 切換幣別 -->
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Admission 錄取查詢</span>
            @if(isset($camp_info->currencies) && $camp_info->currencies->isNotEmpty())
                <div class="d-flex align-items-center">
                    <span class="mr-2 font-weight-bold">幣別 Currency：</span>
                    @foreach($camp_info->currencies as $currency)
                        <div class="custom-control custom-radio custom-control-inline mr-2">
                            <input type="radio" 
                                   id="currency_{{ $currency->code }}" 
                                   name="currency_option" 
                                   class="custom-control-input" 
                                   value="{{ $currency->code }}"
                                   data-id="{{ $currency->id }}"
                                   data-symbol="{{ $currency->symbol }}"
                                   data-xrate="{{ $currency->pivot->xrate_to_std ?? 1 }}"
                                   {{ $currency->code === $defaultCode ? 'checked' : '' }}
                                   onclick="changeCurrency(this.value)">
                            <label class="custom-control-label" for="currency_{{ $currency->code }}">
                                {{ $currency->code }}
                            </label>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="card-body">
            @if($applicant->is_admitted && !$applicant->deleted_at)
                <p class="card-text">Dear {{ $applicant->name }} </p>
                <p class="card-text text-indent">It is our honor to welcome you to 「{{ $camp_info->fullName }}」！We hope you will have a great time in the camp.
                    The following are information you need to know before you come. Please read carefully.
                </p>
                <p class="card-text text-indent">
                Your Application Number 您的報名序號：{{ $applicant->id }}<br>
                Your Admission Number 您的錄取編號：{{ $applicant->group }}{{ $applicant->number }}<br>
                Datas 營隊期間：{{ $applicant->batch->batch_start }} ({{ $applicant->batch->batch_start_weekday }}) ~ {{ $applicant->batch->batch_end }} ({{ $applicant->batch->batch_end_weekday }})，共4天<br>
                Location 營隊地點：{{ $applicant->batch->locationName }} ({{ $applicant->batch->location }})<br>
                </p>

                <h4>Before you come 錄取/報到通知</h4>
                <div class="ml-0 mb-2">
                Please read carefully the <a href="{{ $camp_info->content_link_eng }}" target="_blank">Acceptance Letter</a>. 
                You will find important camp details and payment instructions in the document.
                <br>
                請詳閱 <a href="{{ $camp_info->content_link_chn }}" target="_blank">錄取通知</a>，內含報到資訊、必帶物品，及交通資訊等等。<br>
                </div>
                <br>
                @if(!isset($applicant->is_attend) || $applicant->is_attend)
                    <h4>Registration 活動費用</h4>
                    <form class="ml-2 mb-2" action="{{ route('modifyLodging', $batch_id) }}" method="POST" id="selectLodging">
                        @csrf
                        <div class="ml-0 mb-2">
                            Refer to your <a href="{{ $camp_info->content_link_eng }}" target="_blank">Acceptance Letter</a>
                            for the registration fee options.
                            <br>
                            請參閱您的 <a href="{{ $camp_info->content_link_chn }}" target="_blank">錄取通知</a>。其中有關於活動費用的詳細說明。
                        </div>
                        <br>
                        <input type="hidden" name="id" value="{{ $applicant->applicant_id ?? $applicant->id }}">
                        <input type="hidden" name="camp" value="nycamp">
                        <input type="hidden" name="nights" value="{{ $applicant->lodging?->nights ?? 1}}">
                        <input type="hidden" name="currency_code" class="curr-input" value="{{ $defaultCode }}">
                        <div class='row form-group required'>
                            <label for='inputRoomType' class='col-md-2 control-label text-md-right'>活動費用</label>
                            <div class="col-md-4">
                                <select required class='form-control' name='room_type' id='inputRoomType' onchange='changeRoom(this)'>
                                    <option value='' selected>- 請選擇 -</option>
                                    @foreach($fare_room as $key => $value)
                                    <option value="{{ $key }}" data-label="{{ $key }}" data-base="{{ $value }}">
                                        {{ $key }}({{ $defaultCode }}{{ $defaultSymbol }}{{ $value }})
                                    </option>
                                    @endforeach                                
                                </select>
                                <div class="invalid-feedback">
                                    請選擇活動費用
                                </div>
                            </div>
                        </div>
                        {{-- <div class='row form-group companion-sec required' style='display:none'>
                            <label for='inputCompanion' class='col-md-2 control-label text-md-right'>Friend's name 同行者姓名</label>
                            <div class="col-md-4">
                                @if(isset($applicant->companion_name))
                                <input type='text' class='form-control' name="companion_name" id='inputCompanion' value='{{ $applicant->companion_name }}' >
                                @else
                                <input type='text' class='form-control' name="companion_name" id='inputCompanion' value='' >
                                @endif
                                <div class="invalid-feedback">
                                    Please provide your companion's name. 請提供同行者姓名
                                </div>
                            </div>
                        </div>
                        --}}
                        <input class="btn btn-success" type="submit" value="apply change 確認修改活動費用" id="confirmlodging" name="confirmlodging">
                    </form><br>

                    <h4>Shuttle Bus Service 接駁服務</h4>
                    <form class="ml-2 mb-2" action="{{ route('modifyTraffic', $batch_id) }}" method="POST" id="selecttraffic">
                        @csrf
                            <div class="ml-0 mb-2">

                                Refer to your <a href="{{ $camp_info->content_link_eng }}" target="_blank">Acceptance Letter</a>
                                for the shuttle bus services.
                                <br>
                                請參閱您的 <a href="{{ $camp_info->content_link_chn }}" target="_blank">錄取通知</a>。其中有關於接駁服務的詳細說明。
                            </div>
                        <br>
                        <input type="hidden" name="id" value="{{ $applicant->applicant_id ?? $applicant->id }}">
                        <input type="hidden" name="camp" value="nycamp">
                        <input type="hidden" name="currency_code" class="curr-input" value="{{ $defaultCode }}">
                        <div class='row form-group required'>
                            <label for='inputDepartFrom' class='col-md-2 control-label text-md-right'>去程交通</label>
                            <div class="col-md-4">
                                <select required class='form-control' name='depart_from' id='inputDepartFrom'>
                                    <option value='' selected>- 請選擇 -</option>
                                    @foreach($fare_depart_from as $key => $value)
                                    <option value="{{ $key }}" data-label="{{ $key }}" data-base="{{ $value }}">
                                        {{ $key }}({{ $defaultCode }}{{ $defaultSymbol }}{{ $value }})
                                    </option>
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
                                    <option value='' selected>- 請選擇 -</option>
                                    @foreach($fare_back_to as $key => $value)
                                    <option value="{{ $key }}" data-label="{{ $key }}" data-base="{{ $value }}">
                                        {{ $key }}({{ $defaultCode }}{{ $defaultSymbol }}{{ $value }})
                                    </option>
                                    @endforeach                                
                                </select>
                                <div class="invalid-feedback">
                                    請選擇回程交通
                                </div>
                            </div>
                        </div>
                        <input class="btn btn-success" type="submit" value="confirm change 確認修改交通" id="confirmtraffic" name="confirmtraffic">
                    </form><br>
                    @php
                        $fare_total = ($applicant->traffic?->fare_std ?? 0) + ($applicant->lodging?->fare_std ?? 0);
                        $sum_total = ($applicant->traffic?->deposit_std ?? 0) + ($applicant->traffic?->cash_std ?? 0) + ($applicant->lodging?->deposit_std ?? 0) + ($applicant->lodging?->cash_std ?? 0);
                    @endphp
                    <div class="ml-2 mb-2 alert alert-info" role='alert'>
                        <b>
                        =====&nbsp;&nbsp;&nbsp;Payment Due 應交費用：<span class="curr-code">{{ $defaultCode }}</span> <span class="curr-symbol">{{ $defaultSymbol }}</span><span id="display_fare_total" data-base="{{ $fare_total }}">{{ $fare_total }}</span>&nbsp;&nbsp;&nbsp;=====<br>
                        =====&nbsp;&nbsp;&nbsp;Payment Received 已交費用：<span class="curr-code">{{ $defaultCode }}</span> <span class="curr-symbol">{{ $defaultSymbol }}</span><span id="display_sum_total" data-base="{{ $sum_total }}">{{ $sum_total }}</span>&nbsp;&nbsp;&nbsp;=====<br>
                        </b>
                    </div><br>
                @endif
                    <h4>Cancellation 放棄參加</h4>
                    <form class="ml-2 mb-2" action="{{ route('toggleAttend', $batch_id) }}" method="POST" id="attendcancel">
                        @csrf
                        <input type="hidden" name="id" value="{{ $applicant->applicant_id ?? $applicant->id }}">
                        <input type="hidden" name="camp" value="nycamp">
                        @if(!isset($applicant->is_attend) || $applicant->is_attend )
                            <div class="ml-0 mb-2 text-primary">You will [attend] the camp.</div>
                            <div class="ml-0 mb-2">If for any reasion you decide not to attend, please inform us by pressing the [cancal] button below.</div>
                            <div class="ml-0 mb-2 text-primary">您目前的狀態是「參加」。</div>
                            <div class="ml-0 mb-2">如您因故無法參加，請按下面「放棄參加」通知我們，謝謝！</div>
                            <div>
                            <input class="btn btn-danger" type="submit" value="cancel 放棄參加" id="cancel" name="cancel">
                            </div>
                        @else
                            <div class="ml-0 mb-2 text-danger">You have [cancelled] your registration.</div>
                            <div class="ml-0 mb-2">If the reason(s) preventing you from coming disappeared and you decide to come, simply press the "attend" button to inform us.</div>
                            <div class="ml-0 mb-2 text-danger">您目前的狀態是「放棄參加」。</div>
                            <div class="ml-0 mb-2">如您可以參加了，請按恢復參加，謝謝！</div>
                            <div>
                            <input class="btn btn-success" type="submit" value="attend 恢復參加" id="confirmattend" name="confirmattend">
                            </div>
                        @endif
                    </form><br>
                <h4>Contact 聯絡我們</h4>
                <div class="ml-0 mb-2">If you have any question, feel free to contact</div>
                {!! nl2br(e(str_replace('\n', "\n", $applicant->batch->contact_card))) !!}
                <br><br>
                <div class="ml-0 mb-2">如果您有任何問題，請聯絡</div>
                {!! nl2br(e(str_replace('\n', "\n", $applicant->batch->contact_card))) !!}
                <br><br>
                <p class="card-text">{{ \Carbon\Carbon::now()->format('n/j/Y') }}</p>
            @elseif($applicant->created_at->gte(\Carbon\Carbon::parse('2025-06-11 00:00:00')))
                <!-----錄取中----->
                <p class="card-text">親愛的 {{ $applicant->name }} 同學您好</p>
                <p class="card-text indent">感謝您報名「{{ $camp_info->fullName }}」，錄取作業正在進行中，請稍後再進行錄取查詢。感謝您的耐心等待！</p>
                <p class="card-text indent">Warm regards, </p>
                <p class="card-text indent">The 2027 Life Camp Organizing Team</p>
                <p class="card-text indent">{{ \Carbon\Carbon::now()->format('Y 年 n 月 j 日') }}</p>
            @elseif($applicant->deleted_at)
            @else
                <!-----備取=不錄取----->
                <p class="card-text">親愛的 {{ $applicant->name }} 同學您好</p>
                <p class="card-text indent">非常感謝您報名參加「{{ $camp_info->fullName }}」，由於本活動報名人數踴躍，且場地有限，非常抱歉未能在第一階段錄取您。我們已將您列入優先備取名單，若有遞補機會，基金會將儘速通知您!</p>
                <p class="card-text indent">開學後，各區福青學堂定期都有精彩的課程活動，竭誠歡迎您的參與!也祝福您學業順利，吉祥如意！</p>
                <h4>各區福青學堂資訊</h4>
                <div class="container">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="card-text">
                            台北福青學堂<br>
                            02-2545-3788 #546<br>
                            台北市松山區南京東路四段161號9樓<br>
                            </p>
                        </div>
                    </div>
                </div>
                <p class="card-text text-right">The 2027 Life Camp Organizing Team 敬啟</p>
                <p class="card-text text-right">{{ \Carbon\Carbon::now()->format('Y 年 n 日 j 日') }}</p>
            @endif
            <input type='button' class='btn btn-warning' value='back 回上一頁' onclick=self.history.back()>
            <a href="{{ $camp_info->site_url }}" class="btn btn-primary">home 回營隊首頁</a>
        </div>
    </div>

    <script>
        function changeCurrency(code) {
            const checkedRadio = document.querySelector('input[name="currency_option"]:checked');
            if(!checkedRadio) return;

            const symbol = checkedRadio.getAttribute('data-symbol') || '$';
            const xrate = parseFloat(checkedRadio.getAttribute('data-xrate')) || 1.0;

            // 0. 同步目前幣別到表單的 hidden input
            document.querySelectorAll('.curr-input').forEach(el => el.value = code);
            // 1. 更新幣別代碼與符號
            document.querySelectorAll('.curr-code').forEach(el => el.innerText = code);
            document.querySelectorAll('.curr-symbol').forEach(el => el.innerText = symbol);

            // 2. 更新下拉選單中的金額 (選項金額)
            document.querySelectorAll('option[data-base]').forEach(opt => {
                const baseVal = parseFloat(opt.dataset.base) || 0;
                const label = opt.dataset.label;
                opt.textContent = `${label}(${code}${symbol}${Math.round(baseVal * xrate)})`;
            });

            // 3. 更新接駁車說明內文中的單價金額
            document.querySelectorAll('.shuttle-fare-val').forEach(el => {
                const baseVal = parseFloat(el.getAttribute('data-base')) || 0;
                el.innerText = Math.round(baseVal * xrate);
            });

            // 4. 更新應交/已交總金額
            const fareTotalEl = document.getElementById('display_fare_total');
            if(fareTotalEl) {
                const baseFare = parseFloat(fareTotalEl.getAttribute('data-base')) || 0;
                fareTotalEl.innerText = Math.round(baseFare * xrate);
            }

            const sumTotalEl = document.getElementById('display_sum_total');
            if(sumTotalEl) {
                const baseSum = parseFloat(sumTotalEl.getAttribute('data-base')) || 0;
                sumTotalEl.innerText = Math.round(baseSum * xrate);
            }
        }

        function calcFareTotal() {
            const ids = ['inputRoomType', 'inputDepartFrom', 'inputBackTo'];
            let base = 0;

            ids.forEach(id => {
                const sel = document.getElementById(id);
                if (!sel || !sel.value) return;               // 還沒選就不加
                const opt = sel.options[sel.selectedIndex];
                base += parseFloat(opt.dataset.base) || 0;
            });

            const el = document.getElementById('display_fare_total');
            if (!el) return;

            el.setAttribute('data-base', base);               // 更新基準金額
            const checked = document.querySelector('input[name="currency_option"]:checked');
            const xrate = parseFloat(checked?.dataset.xrate) || 1;
            el.innerText = Math.round(base * xrate);
        }
        
        @if(!isset($applicant->is_attend) || $applicant->is_attend)
            let cancel = document.getElementById('cancel');
            if(cancel) {
                cancel.addEventListener('click', function(event) {
                    if(confirm('confirm cancellation 確認放棄參加？')){
                        return true;
                    }
                    event.preventDefault();
                    return false;
                });
            }
        @else
            let confirmattend = document.getElementById('confirmattend');
            if(confirmattend) {
                confirmattend.addEventListener('click', function(event) {
                    if(confirm('confirm 確認恢復參加？')){
                        return true;
                    }
                    event.preventDefault();
                    return false;
                });
            }
        @endif

        @if(!isset($applicant->is_attend) || $applicant->is_attend)
            let confirmtraffic = document.getElementById('confirmtraffic');
            if(confirmtraffic) {
                confirmtraffic.addEventListener('click', function(event) {
                    if(confirm('confirm 確認修改交通？')){
                        return true;
                    }
                    event.preventDefault();
                    return false;
                });
            }

            {{-- 回填交通選項 --}}
            (function() {
                let traffic_data = JSON.parse('{!! $applicant_data ?? '{}' !!}');

                // 先依 fare_currency_id 還原幣別，這樣之後的選項文字才是正確的幣別
                if (traffic_data.fare_currency_id) {
                    const radio = document.querySelector(
                        `input[name="currency_option"][data-id="${traffic_data.fare_currency_id}"]`
                    );
                    if (radio) {
                        radio.checked = true;
                        changeCurrency(radio.value);
                    }
                }

                let selects = document.getElementsByTagName('select');
                for (var i = 0; i < selects.length; i++){
                    if(typeof traffic_data[selects[i].name] !== "undefined"){
                        selects[i].value = traffic_data[selects[i].name];
                    }
                }
            })();

            let confirmlodging = document.getElementById('confirmlodging');
            if(confirmlodging) {
                confirmlodging.addEventListener('click', function(event) {
                    if(confirm('confirm 確認修改活動費？')){
                        return true;
                    }
                    event.preventDefault();
                    return false;
                });
            }

            {{-- 回填活動費選項 --}}
            (function() {
                let lodging_data = JSON.parse('{!! $applicant_data ?? '{}' !!}');

                // 先依 fare_currency_id 還原幣別，這樣之後的選項文字才是正確的幣別
                if (lodging_data.fare_currency_id) {
                    const radio = document.querySelector(
                        `input[name="currency_option"][data-id="${lodging_data.fare_currency_id}"]`
                    );
                    if (radio) {
                        radio.checked = true;
                        changeCurrency(radio.value);
                    }
                }

                let selects = document.getElementsByTagName('select');
                for (var i = 0; i < selects.length; i++){
                    if(typeof lodging_data[selects[i].name] !== "undefined"){
                        selects[i].value = lodging_data[selects[i].name];
                        changeRoom(selects[i]);
                    }
                }
            })();
        @endif

        function changeRoom(select_ele) {
            const companionSection = document.getElementsByClassName('companion-sec')[0];
            const companionInput = document.getElementById('inputCompanion');

            if (companionSection && companionInput) {
                if (select_ele.value.includes("兩人同行")) {
                    companionSection.style.display = '';
                    companionInput.required = true;
                } else {
                    companionSection.style.display = 'none';
                    companionInput.required = false;
                }
            }
        };

        ['inputRoomType', 'inputDepartFrom', 'inputBackTo'].forEach(id => {
            document.getElementById(id)?.addEventListener('change', calcFareTotal);
        });

        // 頁面載入後初始化：先還原幣別，再算一次總額
        //changeCurrency('{{ $currency_sel->code }}');
        calcFareTotal();
    </script>
@stop