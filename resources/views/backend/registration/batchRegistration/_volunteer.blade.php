@extends('backend.master')
{{-- 欄位簡單的義工營共用（姓名、性別、email、手機、區域、我是誰），各營隊的 view 直接 extends 這裡 --}}
@section('content')
    @php
        $regions = ['總部', '台北', '桃園', '新竹', '台中', '雲嘉', '台南', '高雄', '其它'];
        $rows = old('applicants', [[]]);
        // 預設寄信；送出後沿用上次的選擇
        $sendMail = old('send_mail', request('send_mail', 1));
    @endphp
    <h2>{{ $campFullData->fullName }} 批次報名</h2>
    <p>梯次：{{ $targetBatch->name }}，{{ $targetBatch->batch_start }} ~ {{ $targetBatch->batch_end }}</p>

    @if(isset($results))
        <div class="alert alert-success">
            本次送出 {{ count($results) }} 人，新報名 {{ collect($results)->where('isRepeat', false)->count() }} 人{{ request()->boolean('send_mail') ? '，已寄送報名確認信' : '，未寄送報名確認信' }}。
        </div>
        <table class="table table-sm table-bordered mb-4">
            <thead><tr><th>報名序號</th><th>姓名</th><th>Email</th><th>結果</th></tr></thead>
            <tbody>
                @foreach($results as $result)
                    <tr>
                        <td>{{ $result['applicant']->id }}</td>
                        <td>{{ $result['applicant']->name }}</td>
                        <td>{{ $result['applicant']->email }}</td>
                        <td>
                            @if($result['isRepeat'])
                                <span class="text-warning">已報名過，未重複建立</span>
                            @else
                                <span class="text-success">報名成功</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <h4>繼續報名</h4>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            資料有誤，請修正後再送出：
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ route('batchRegistration', $camp_id) }}" id="batchForm">
        @csrf
        <input type="hidden" name="target_batch_id" value="{{ $targetBatch->id }}">
        <div id="rows">
            @foreach($rows as $i => $row)
                <div class="card mb-2 applicant-row">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between mb-1">
                            <strong>第 <span class="row-no">{{ $loop->iteration }}</span> 位</strong>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-row">刪除</button>
                        </div>
                        <div class="form-row">
                            <div class="col-md-2 mb-1">
                                <input type="text" class="form-control form-control-sm" data-field="name" name="applicants[{{ $i }}][name]" value="{{ $row['name'] ?? '' }}" placeholder="姓名（全名）" required>
                            </div>
                            <div class="col-md-2 mb-1">
                                <select class="form-control form-control-sm" data-field="gender" name="applicants[{{ $i }}][gender]" required>
                                    <option value="">- 生理性別 -</option>
                                    <option value="M" @selected(($row['gender'] ?? '') == 'M')>男</option>
                                    <option value="F" @selected(($row['gender'] ?? '') == 'F')>女</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-1">
                                <input type="email" class="form-control form-control-sm" data-field="email" name="applicants[{{ $i }}][email]" value="{{ $row['email'] ?? '' }}" placeholder="電子郵件" required>
                            </div>
                            <div class="col-md-2 mb-1">
                                <input type="tel" class="form-control form-control-sm" data-field="mobile" name="applicants[{{ $i }}][mobile]" value="{{ $row['mobile'] ?? '' }}" placeholder="行動電話" required>
                            </div>
                            <div class="col-md-3 mb-1">
                                <select class="form-control form-control-sm" data-field="region" name="applicants[{{ $i }}][region]" required>
                                    <option value="">- 區域 -</option>
                                    @foreach($regions as $region)
                                        <option value="{{ $region }}" @selected(($row['region'] ?? '') == $region)>{{ $region }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 mb-1">
                                <input type="text" class="form-control form-control-sm" data-field="self_intro" name="applicants[{{ $i }}][self_intro]" value="{{ $row['self_intro'] ?? '' }}" placeholder="我是誰：例如第X組輔導員、教務組、報到組等" required>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <button type="button" class="btn btn-outline-primary mb-3" id="addRow">＋ 新增一位</button>
        <div class="form-check mb-3">
            <input type="hidden" name="send_mail" value="0">
            <input type="checkbox" class="form-check-input" name="send_mail" value="1" id="sendMail" @checked($sendMail)>
            <label class="form-check-label" for="sendMail">寄送報名確認信給報名者</label>
        </div>
        <div>
            <button type="submit" class="btn btn-success" onclick="return confirm('確定送出 ' + document.querySelectorAll('.applicant-row').length + ' 位的報名資料？')">確認送出</button>
        </div>
    </form>

    <script>
        (function () {
            const rows = document.getElementById('rows');

            // 重新編號，讓 name 的 index 連續
            function renumber() {
                rows.querySelectorAll('.applicant-row').forEach(function (row, i) {
                    row.querySelector('.row-no').textContent = i + 1;
                    row.querySelectorAll('[data-field]').forEach(function (el) {
                        el.name = 'applicants[' + i + '][' + el.dataset.field + ']';
                    });
                });
            }

            // 新增一位：複製最後一列，清空個人欄位，保留區域與「我是誰」方便同組連續填寫
            document.getElementById('addRow').addEventListener('click', function () {
                const last = rows.querySelector('.applicant-row:last-child');
                const clone = last.cloneNode(true);
                clone.querySelectorAll('[data-field]').forEach(function (el) {
                    if (['region', 'self_intro'].includes(el.dataset.field)) {
                        el.value = last.querySelector('[data-field="' + el.dataset.field + '"]').value;
                    } else {
                        el.value = '';
                    }
                });
                rows.appendChild(clone);
                renumber();
                clone.querySelector('[data-field="name"]').focus();
            });

            rows.addEventListener('click', function (e) {
                if (!e.target.classList.contains('remove-row')) {
                    return;
                }
                if (rows.querySelectorAll('.applicant-row').length == 1) {
                    alert('至少需保留一位。');
                    return;
                }
                e.target.closest('.applicant-row').remove();
                renumber();
            });
        })();
    </script>
@endsection
