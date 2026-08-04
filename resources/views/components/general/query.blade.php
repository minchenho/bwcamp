@extends('camps.' . $camp_info->table . '.layout')
@section('content')
@if($errors->any())
    @foreach ($errors->all() as $message)
        <div class='alert alert-danger' role='alert'>
            {{ $message }}
        </div>
    @endforeach
@endif

@if($use_eng)
	<br>
	<div class='alert alert-info' role='alert'>
	    Instruction: 
	    If you provide Chinese Name upon registration, type Chinese Name in (Last First) order without space. (例如：王小花) <br>
	    Otherwise, type English Name in (First Last) order. Use only single space to separate. (for example, Peter Wu)<br>
	</div>
@endif

<form method="post" action="{{ route("queryview", $batch_id) }}" name="QueryRegis" class="form-horizontal">
    @csrf
    {{-- 如果有batch_id_from參數，表示是從報名網站傳過來的，就繼續pass下去 --}}
    @if(isset($batch_id_from))
        <input type="hidden" name="batch_id_from" value="{{ $batch_id_from }}">
    @endif
    <div class="page-header form-group">
        <h4>
		@if($use_eng) Query Registration @endif
		報名資料查詢
		</h4>
    </div>
    <div class='row form-group'>
        <label for='inputName' class='col-md-2'>
		@if($use_eng) Name<br>@endif
		姓名
		</label>
        <div class='col-md-10'>
            <input type='text' name='name' class='form-control' id='inputName' placeholder='' value="{{ old('name') }}" required>
        </div>
    </div>

    <div class="row form-group">
        <label for='inputSN' class='col-md-2'>
		@if($use_eng) Registration number<br>@endif
		報名序號
		</label>
        <div class='col-md-10'>
            <input type='text' name='sn' class='form-control' id='inputSN' maxlength=5 placeholder='' value="{{ old('sn') }}" required>
        </div>
    </div>

    {{-- <div class="row form-group">
        <label for='inputRecap' class='col-md-2 control-label'></label>
        <div class='col-md-8'>
        <div class='g-recaptcha' data-sitekey='6Lc6sdASAAAAACovaErznXN6DikqaOlqoVw2SEUK'></div>
        <script type='text/javascript' src='https://www.google.com/recaptcha/api.js?hl=zh-TW'>
        </script>
        </div>
    </div> --}}

    <!--- 確認送出 -->
    <div class=row>
        <div class='col-md-4'></div>
        <div class='col-md-8'>
            <INPUT type=submit name=sub class='btn btn-primary' 
			value='@if($use_eng)submit @endif 查詢資料'>
            <INPUT type=reset  class='btn btn-danger' 
			value='@if($use_eng)clear @endif 清除重來'>
        </div>
    </div>
</form>

@if(!isset($batch_id_from))
<form method="post" action="{{ route("queryupdate", $batch_id) }}" name="updateRegis" class="form-horizontal">
    @csrf
    <input type="hidden" name="isModify" value="1">
    <div class="page-header form-group">
        <h4>
		@if($use_eng) Modify Registration @endif
		報名資料修改
		</h4>
    </div>
    <div class='row form-group'>
        <label for='inputName' class='col-md-2'>
		@if($use_eng) Name<br> @endif
		姓名
		</label>
        <div class='col-md-10'>
            <input type='text' name='name' class='form-control' id='inputName' placeholder='' value='{{ old('name') }}' required>
        </div>
    </div>

    <div class="row form-group">
        <label for='inputSN' class='col-md-2'>
		@if($use_eng) Registration no<br> @endif
		報名序號
		</label>
        <div class='col-md-10'>
            <input type='text' name='sn' class='form-control' id='inputSN' maxlength=5 placeholder='' value="{{ old('sn') }}" required>
        </div>
    </div>

    {{-- <div class="row form-group">
        <label for='inputRecap' class='col-md-2 control-label'></label>
        <div class='col-md-8'>
        <div class='g-recaptcha' data-sitekey='6Lc6sdASAAAAACovaErznXN6DikqaOlqoVw2SEUK'></div>
        <script type='text/javascript' src='https://www.google.com/recaptcha/api.js?hl=zh-TW'>
        </script>
        </div>
    </div> --}}

    <!--- 確認送出 -->
    <div class=row>
        <div class='col-md-4'></div>
        <div class='col-md-8'>
            <INPUT type=submit name=sub class='btn btn-success' 
			value='@if($use_eng)submit @endif 修改資料'>
            <INPUT type=reset  class='btn btn-danger' 
			value='@if($use_eng)clear @endif 清除重來'>
        </div>
    </div>
</form>
@endif

<form method="post" name="QuerySN" action="{{ route('querysn', $batch_id) }}" class="form-horizontal">
    @csrf
    <div class="page-header form-group">
        <h4>
		@if($use_eng) Query Registration No @endif
		報名序號查詢
		</h4>
    </div>
@if($use_eng)	
    <label class='text-info'>
        The registration number will be sent to the email address you provide.<br>
    </label>
@endif
    <div class="row form-group">
        <label for='inputName2' class='col-md-2'>
		@if($use_eng) Name<br> @endif
		姓名
		</label>
        <div class='col-md-10'>
        <input type='text' name="name" class='form-control' id='inputName2' placeholder=''>
        </div>
    </div>
@if($use_mobile)
    <div class="row form-group">
        <label for='inputMobile' class='col-md-2'>手機</label>
        <div class='col-md-10'>
        <input type='tel' required name="mobile" class='form-control' id='inputMobile' placeholder='格式：0912345678'>
        </div>
    </div>
@else
    <div class='row form-group'>
        <label for='inputBirth' class='col-md-2'>
		@if($use_eng) Birth year<br> @endif
		生日
		</label>
        <div class='date col-md-10' id='inputBirth'>
            <div class='row form-group required'>
                <div class="col-md-1 text-md-right">
                    西元
                </div>
                <div class="col-md-2">
                    <input type='number' required class='form-control' name='birthyear' min={{ $year_min }} max={{ $year_max }} value='' placeholder=''>
                </div>
                <div class="col-md-1">
                    年
                </div>
                @if($birthday_use_month)
                <div class="col-md-2">
                    <input type='number' required class='form-control' name='birthmonth' min=1 max=12 value='' placeholder=''>
                </div>
                <div class="col-md-1">
                    月
                </div>
				@endif
                @if($birthday_use_day)
                <div class="col-md-2">
                    <input type='number' required class='form-control' name='birthday' min=1 max=31 value='' placeholder=''>
                </div>
                <div class="col-md-1">
                    日
                </div>
                @endif
            </div>
            <div class='help-block with-errors'></div>
        </div>
    </div>
@endif
    <!--- 確認送出 -->
    <div class=row>
        <div class='col-md-4'></div>
        <div class='col-md-8'>
        <INPUT type=submit name=sub class='btn btn-info' 
		value='@if($use_eng) submit @endif 查詢序號'>
        </div>
    </div>
</form>
@stop