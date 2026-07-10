@extends('backend.master')
@section('content')
    <style>
        .card-link{
            color: #3F86FB!important;
        }
        .card-link:hover{
            color: #33B2FF!important;
        }
        /* customize */
        .form-group.required .control-label:after {
            content: "＊";
            color: red;
        }
    </style>
    
    <h2>{{ $camp->abbreviation }} 修改組織職務</h2>
    
    <form action="{{ route('modifyOrg', [$camp->id, $org->id]) }}" method="POST">
        @csrf
        
        {{-- 1. 梯次設定 --}}
        <div class='row form-group'>
            <label for='inputBatch' class='col-md-2 control-label'>梯次</label>
            <div class='col-md-6'>
                <select name="batch_id" class="form-control">
                    <option value="">不限</option>
                    <optgroup label="學員">
                        @foreach($camp->batches ?? [] as $batch) 
                            <option value="{{ $batch->id }}" {{ $batch->id == $org->batch_id ? "selected" : "" }}>學員 - {{ $batch->name }}</option>
                        @endforeach
                    </optgroup>
                    @if($camp->vcamp)
                        <optgroup label="義工">
                            @foreach($camp->vcamp->batches ?? [] as $batch)
                                <option value="{{ $batch->id }}" {{ $batch->id == $org->batch_id ? "selected" : "" }}>義工 - {{ $batch->name }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                </select>
            </div>
        </div>

        {{-- 2. 區域設定 --}}
        <div class='row form-group'>
            <label for='inputRegion' class='col-md-2 control-label'>區域</label>
            <div class='col-md-6'>
                <select name="region_id" class="form-control">
                    <option value="">不限</option>
                    @foreach($camp->regions ?? [] as $region)
                        <option value="{{ $region->id }}" {{ $region->id == $org->region_id ? "selected" : "" }}>{{ $region->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- 3. 上層組織路徑顯示 --}}
        <div class='row form-group'>
            <label class='col-md-2 control-label'>組織上層</label>
            <div class='col-md-6 pt-2 text-muted'>
                @if($org->isRoot())
                    <span class="badge badge-danger">此職務為大會頂層根節點</span>
                @else
                    <strong>{{ $org->getPathString() }}</strong>
                    {{-- 只要不是根節點、且不是無主孤兒，就顯示搬移按鈕 --}}
                    @if(!is_null($org->prev_id))
                        <button type="button" id="btn_toggle_move" class="btn btn-sm btn-outline-primary ml-3">
                            <i class="fa fa-arrows-alt"></i> 移動組織
                        </button>
                    @endif
                @endif
            </div>
        </div>

        {{-- 只有非根節點才能更改上層 --}}
        @if(!$org->isRoot())
            {{-- 如果是無主職務 (prev_id 為 null) 就不隱藏，否則預設 display: none 藏起來 --}}
            <div class='row form-group required' id="move_org_section" style="{{ is_null($org->prev_id) ? '' : 'display: none;' }}">
                <label class='col-md-2 control-label'>更改上層組織</label>
                <div class='col-md-6'>
                    <select name="prev_id" class="form-control" required>
                        <option value="">- 請選擇新的上層組織 -</option>
                        @foreach($camp->organizations()->get() as $parentOrg)
                            @if($parentOrg->id !== $org->id && $parentOrg->prev_id)
                                <option value="{{ $parentOrg->id }}" {{ $parentOrg->id == $org->prev_id ? 'selected' : '' }}>
                                    {{ $parentOrg->getPathString() }} > {{ $parentOrg->position }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                    @if(is_null($org->prev_id))
                        <small class="text-danger font-weight-bold">⚠️ 此職務目前處於斷鏈(無主)狀態，請務必重新選擇上層組織！</small>
                    @else
                        <small class="text-muted">您可以透過更改此選項，將此組別隨時搬移到其他大組下方。</small>
                    @endif
                </div>
            </div>
        @endif

        {{-- 4. 職務名稱與排序 --}}
        @if(!$org->isRoot())
            <div class='row form-group'>
                <label for='inputPos' class='col-md-2 control-label'>職務名稱</label>
                <div class='col-md-6'>
                    <input type="text" name="position" class='form-control' value="{{ $org->position ?? '' }}" required>
                </div>
            </div>
            
            <div class='row form-group'>
                <label for='inputOrder' class='col-md-2 control-label'>顯示排序<br>(組織代號)</label>
                <div class='col-md-6'>
                    <input type="number" name="order" class='form-control' value="{{ $org->order ?? 0 }}" required>
                </div>
            </div>

            {{-- 5. 綁定的學員組別 --}}
            <div class='row form-group'>
                <label class='col-md-2 control-label'>綁定的學員組別</label>
                <div class='col-md-6'>
                    <div class="form-check mb-2">
                        <input type="radio" name="all_group" id="group_mode_none" class="form-check-input" value="none" 
                            @checked(is_null($org->group_id))>
                        <label class="form-check-label" for="group_mode_none">不綁定小組</label>
                    </div>

                    <div class="row mb-2 align-items-center">
                        <div class="col-4">
                            <div class="form-check">
                                <input type="radio" name="all_group" id="group_mode_single" class="form-check-input" value="single" 
                                    @checked(!is_null($org->group_id) && $org->group_id !== 0)>
                                <label class="form-check-label" for="group_mode_single">單一學員組別</label>
                            </div>
                        </div>
                        <div class="col-8">
                            <select name="group_id" class="form-control">
                                <option value="">- 請選擇組別 -</option>
                                @foreach($camp->groups ?? [] as $group)
                                    <option value="{{ $group->id }}" {{ $group->id == $org->group_id ? "selected" : "" }}>{{ $group->alias }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-check">
                        <input type="radio" name="all_group" id="group_mode_all" class="form-check-input" value="all" 
                            @checked($org->group_id === 0)>
                        <label class="form-check-label" for="group_mode_all">全部學員組別 (跨組權限)</label>
                    </div>
                </div>
            </div>

            {{-- ✨ 6. 新增：Laratrust 權限矩陣開關按鈕 --}}
            <div class='row form-group mt-4'>
                <label class='col-md-2 control-label'>後台權限設定</label>
                <div class='col-md-6'>
                    <button type="button" id="btn_toggle_permission" class="btn btn-outline-warning">
                        <i class="fa fa-key"></i> 展開/折疊 權限設定明細
                    </button>
                    <small class="form-text text-muted">點擊按鈕可單獨展開長長的權限勾選矩陣表格。</small>
                </div>
            </div>

            {{-- ✨ 核心改動：用 div 包裹 permission_table 並預設隱藏 --}}
            <div class='row form-group' id="permission_section" style="display: none;">
                <div class='col-12 mt-2'>
                    @include('backend.camp.permission_table')
                </div>
            </div>
        @else
            {{-- 如果是根節點，保持其 position 欄位封包完整 --}}
            <input type='hidden' name='position' value='{{ $org->position }}'>
            <input type='hidden' name='order' value='{{ $org->order }}'>
        @endif

        <div class="mt-4">
            <input type="submit" class="btn btn-success" value="確認修改">
            <a href="{{ route('showOrgs', $camp->id) }}" class="btn btn-danger">取消修改</a>
        </div>
    </form>

    {{-- ✨ 更新後的 JavaScript 聯動整合 --}}
    <script>
        $(document).ready(function(){
            // --- 點擊「移動組織」按鈕的滑出/收合邏輯 ---
            $("#btn_toggle_move").click(function(){
                $("#move_org_section").slideToggle(200);
                $(this).toggleClass('btn-outline-primary btn-primary');
            });

            // --- ✨ 新增：點擊「調整權限設定」按鈕的滑出/收合邏輯 ---
            $("#btn_toggle_permission").click(function(){
                $("#permission_section").slideToggle(250); // 稍微給它一點點滑動的動畫時間
                $(this).toggleClass('btn-outline-warning btn-warning'); // 切換成實心橘黃色反饋
            });

            // --- 三選一小組聯動初始化 ---
            if (!$("#group_mode_single").is(":checked")) {
                $("select[name='group_id']").prop("disabled", true);
            }

            // 三選一 Radio 變更時
            $("input[name='all_group']").change(function(){
                let mode = $(this).val();
                if (mode === 'single') {
                    $("select[name='group_id']").prop("disabled", false).focus();
                } else {
                    $("select[name='group_id']").val("").prop("disabled", true);
                }
            });

            // 選單手動選擇時自動打勾單一 Radio
            $("select[name='group_id']").change(function(){
                if ($(this).val() !== "") {
                    $("#group_mode_single").prop("checked", true).trigger('change');
                }
            });
        });
    </script>
@endsection