@extends('camps.' . $camp_info->table . '.layout')
@section('content')
@if($errors->any())
    @foreach ($errors->all() as $message)
        <div class='alert alert-danger' role='alert'>
            {{ $message }}
        </div>
    @endforeach
@endif
@php
	$today = \Carbon\Carbon::now();
	$this_year = $today->year;
@endphp
<form method="post" action="{{ route("queryadmit", $batch_id) }}" name="QueryRegis" class="form-horizontal">
    @csrf
    <div class="page-header form-group">
        @if($today->gte($camp_info->certificate_available_date))
        <h4>下載研習證明</h4>
        @else
        <h4>錄取查詢</h4>
        @endif
    </div>
    <div class='row form-group'>
        <label for='inputName' class='col-md-2'>姓名</label>
        <div class='col-md-10'>
            <input type='text' name='name' class='form-control' id='inputName' placeholder='' value='{{ old('name') }}' required>
        </div>
    </div>

    <div class="row form-group">
        <label for='inputSN' class='col-md-2'>報名序號</label>
        <div class='col-md-10'>
            <input type='text' name='sn' class='form-control' id='inputSN' maxlength=5 placeholder='' value='{{ old('sn') }}' required>
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
        @if($today->gte($camp_info->certificate_available_date))
            <INPUT type=submit name=sub class='btn btn-success' value='送出查詢'>
        @else
            <INPUT type=submit name=sub class='btn btn-success' value='錄取查詢'>
        @endif
            <INPUT type=reset  class='btn btn-danger' value='清除重來'>
        </div>
    </div>
</form>
@stop