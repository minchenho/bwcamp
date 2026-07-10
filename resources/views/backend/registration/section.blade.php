@extends('backend.master')
@section('content')
    <div>
        {{-- ✨ 關鍵優化：拋棄舊 section，利用 getPathString() 漂亮的印出完整樹狀路徑 --}}
        <h2 class="d-inline-block">
            {{ $campFullData->abbreviation }} {{ $batch->name }} 
            [{{ $org->getPathString() }} > {{ $org->position }}] 名單
        </h2>
    </div>
    
    <form action="" method="post" name="sendEmailByGroup">
        <input type="hidden" name="org_id" value="{{ $org->id }}">
        <table class="table table-bordered">
            @csrf
            <thead>
                <tr>
                    <th>報名序號</th>
                    <th>職務</th>
                    <th>姓名</th>
                    <th>生理性別</th>
                    <th>選取<br>全選<input type="checkbox" name="selectAll" onclick="toggler()"></th>
                </tr>
            </thead>
            @foreach ($applicants as $applicant)
                <tr>
                    <td>{{ $applicant->id }}</td>
                    {{-- ✨ 關鍵修正：移除舊 section 拼接，直接顯示職務名稱即可 --}}
                    <td>{{ $org->position }}</td>
                    <td>{{ $applicant->name }}</td>
                    <td>{{ $applicant->gender }}</td>
                    <td>
                        <input type="checkbox" name="sns[]" value="{{ $applicant->id }}" class="selected">
                    </td>
                </tr>
            @endforeach
        </table>

        @if(Session::has("message"))
            <div class="alert alert-success" role="alert">
                {{ Session::get("message") }}
            </div>
        @endif
        @if(Session::has("error"))
            <div class="alert alert-danger" role="alert">
                {{ Session::get("error") }}
            </div>
        @endif

        <button type="submit" class="btn btn-success" style="margin-bottom: 15px" onclick="this.innerText = '處理中'; this.disabled = true; document.sendEmailByGroup.action='{{ route('sendAdmittedMail', $camp_id) }}'; document.sendEmailByGroup.submit();">寄送錄取通知信 / 分組通知函</button>
        <button type="submit" class="btn btn-info float-right" style="margin-bottom: 15px" onclick="this.innerText = '處理中'; this.disabled = true; document.sendEmailByGroup.action='{{ route('sendCheckInMail', $camp_id) }}';document.sendEmailByGroup.submit();">寄送報到通知信</button>
    </form>

<script>
    function toggler(){
        let sns = document.getElementsByClassName("selected");
        // 💡 阿順的極速優化：原本全選按鈕點第二次時會失效（因為單純反轉），這裡改成依據「全選核取方塊」的當前狀態同步更新所有子方塊，邏輯最防彈！
        let selectAllChecked = document.getElementsByName("selectAll")[0].checked;
        for(let i = 0; i < sns.length ; i++){
            sns[i].checked = selectAllChecked;
        }
    }
</script>
@endsection