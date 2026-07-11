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

    <h2>{{ $camp->abbreviation }} 新增組織職務</h2>    
    
    {{-- 頂部目前組織提示區 --}}
    <table class="table table-bordered">
        <tr>
            <td>
                @if($org_tg->id > 0)
                    <strong>[{{ $org_tg->getPathString() }} > {{ $org_tg->position }}]</strong> 目前子職務：
                    @foreach($orgs as $org)
                        {{ $org->position }}、
                    @endforeach
                @else
                    目前頂層大組：
                    @foreach($orgs as $org)
                        {{ $org->position }}、
                    @endforeach
                @endif
            </td>
        </tr>
    </table>

    <form action="{{ route('addOrgs', $camp->id) }}" method="POST">
        @csrf
        <table class="table table-bordered" id="org-table">
            <thead>
                <tr>
                    <th>選擇梯次</th>
                    <th>選擇區域</th>
                    <th>組織上層</th>
                    <th>新增大組/小組/職務名稱</th>
                    <th>顯示排序 (代號)</th>
                    <th>動作</th>
                </tr>
            </thead>
            <tbody>
                <tr class="align-middle">
                    {{-- 預設第一行 --}}
                    <td class="align-middle">
                    @if($org_tg->id == 0)
                        <select required class='form-control' name='batch_id[0]'>
                            <option value=''>- 請選擇 -</option>
                            <option value='0'>不限</option>
                            @foreach($batches as $batch)
                                <option value='{{$batch->id}}'>{{$batch->name}}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="hidden" name="batch_id[0]" value="{{$batch_tg->id}}">{{$batch_tg->name}}
                    @endif
                    </td>

                    <td class="align-middle">
                    @if($org_tg->id == 0)
                        <select required class='form-control' name='region_id[0]'>
                            <option value=''>- 請選擇 -</option>
                            <option value='0'>不限</option>
                            @foreach($regions as $region)
                                <option value='{{$region->name}}'>{{$region->name}}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="hidden" name="region_id[0]" value="{{$region_tg->id}}">{{$region_tg->name}}
                    @endif
                    </td>

                    <td class="align-middle">
                        <input type="hidden" name="prev_id[0]" value="{{$org_tg->id}}">
                        @if($org_tg->id == 0)
                            無 (建立為頂層大組)
                        @else
                            {{ $org_tg->position }}
                        @endif
                    </td>

                    <td class="align-middle">
                        <input required type="text" name="position[0]" class="form-control" placeholder="請輸入職務名稱">
                    </td>

                    <td class="align-middle">
                        <input required type="number" name="order[0]" class="form-control" value="0">
                    </td>

                    <td class="align-middle">
                        {{-- 傳入 mode: 1 是無上層, 2 是有上層 --}}
                        <a href="#" class="btn btn-primary" onclick="addLine({{ $org_tg->id == 0 ? 1 : 2 }}); return false;">+</a>
                    </td>
                </tr>
            </tbody>
        </table>

        <input type="submit" class="btn btn-success" value="確認送出">
        <a href="{{ route('showOrgs', $camp->id) }}" class="btn btn-danger">取消新增</a>
    </form>

    {{-- ========================================== --}}
    {{-- ✨ 核心：HTML 範本區（瀏覽器預設不渲染，專供 JS 複製使用） --}}
    {{-- ========================================== --}}
    
    {{-- 範本 1：無上層 (Mode 1) --}}
    <template id="row-template-mode1">
        <tr class="align-middle">
            <td class="align-middle">
                <select required class='form-control' name='batch_id[__INDEX__]'>
                    <option value=''>- 請選擇 -</option>
                    <option value='0'>不限</option>
                    @foreach($batches as $batch)
                        <option value='{{$batch->id}}'>{{$batch->name}}</option>
                    @endforeach
                </select>
            </td>
            <td class="align-middle">
                <select required class='form-control' name='region_id[__INDEX__]'>
                    <option value=''>- 請選擇 -</option>
                    <option value='0'>不限</option>
                    @foreach($regions as $region)
                        <option value='{{$region->name}}'>{{$region->name}}</option>
                    @endforeach
                </select>
            </td>
            <td class="align-middle">
                <input type="hidden" name="prev_id[__INDEX__]" value="{{$org_tg->id}}">
                無 (建立為頂層大組)
            </td>
            <td class="align-middle">
                <input required type="text" name="position[__INDEX__]" class="form-control" placeholder="請輸入職務名稱">
            </td>
            <td class="align-middle">
                <input required type="number" name="order[__INDEX__]" class="form-control" value="0">
            </td>
            <td class="align-middle">
                <a href="#" class="btn btn-danger" onclick="this.parentNode.parentNode.remove(); return false;">Ｘ</a>
            </td>
        </tr>
    </template>

    {{-- 範本 2：有上層 (Mode 2) --}}
    <template id="row-template-mode2">
        <tr class="align-middle">
            <td class="align-middle">
                <input type="hidden" name="batch_id[__INDEX__]" value="{{$batch_tg->id ?? 0}}">
                {{$batch_tg->name ?? ''}}
            </td>
            <td class="align-middle">
                <input type="hidden" name="region_id[__INDEX__]" value="{{$region_tg->id ?? 0}}">
                {{$region_tg->name ?? ''}}
            </td>
            <td class="align-middle">
                <input type="hidden" name="prev_id[__INDEX__]" value="{{$org_tg->id}}">
                {{$org_tg->position}}
            </td>
            <td class="align-middle">
                <input required type="text" name="position[__INDEX__]" class="form-control" placeholder="請輸入職務名稱">
            </td>
            <td class="align-middle">
                <input required type="number" name="order[__INDEX__]" class="form-control" value="0">
            </td>
            <td class="align-middle">
                <a href="#" class="btn btn-danger" onclick="this.parentNode.parentNode.remove(); return false;">Ｘ</a>
            </td>
        </tr>
    </template>

    {{-- ========================================== --}}
    {{-- ✨ 全新現代化 JavaScript 邏輯 --}}
    {{-- ========================================== --}}
    <script>
        var g_pos_idx = 1; // 陣列索引計數器

        function addLine(mode) {
            // 1. 取得對應的 Template
            const templateId = mode === 1 ? 'row-template-mode1' : 'row-template-mode2';
            const template = document.getElementById(templateId);
            
            // 2. 複製 Template 的內容 (Deep Clone)
            const clone = template.content.cloneNode(true);
            
            // 3. ✨ 核心取代：把範本裡的 `__INDEX__` 換成當前的數字，確保後台收得到 batch_id[1]、batch_id[2]
            const htmlString = clone.querySelector('tr').outerHTML.replace(/__INDEX__/g, g_pos_idx);
            
            // 4. 將乾淨漂亮的真正的 HTML 直接塞進表格的 tbody 中
            const tbody = document.querySelector('#org-table tbody');
            tbody.insertAdjacentHTML('beforeend', htmlString);
            
            // 5. 索引值遞增
            g_pos_idx++;
        }
    </script>
@stop