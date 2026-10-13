@extends('backend.master')
@vite('resources/js/app.js')
@section('content')
    <style>
        .card-link { color: #3F86FB!important; }
        .card-link:hover { color: #33B2FF!important; }
        .tree-row { padding: 8px; border-bottom: 1px solid #eee; display: flex; align-items: center; }
        .tree-row:hover { background-color: #f8f9fa; }
        .tree-container { background: #fff; border: 1px solid #dee2e6; border-radius: 4px; padding: 15px; margin-bottom: 20px; }
        details summary { cursor: pointer; outline: none; font-weight: bold; padding: 8px 0; }
        details details { border-left: 2px dashed #ddd; margin-left: 10px; }
    </style>

    <h2 class="d-inline-block">{{ $camp->abbreviation }} 組織職務樹狀架構清單</h2><br>
    
    @if(\Session::has('error'))
        <div class='alert alert-danger' role='alert'>{{ \Session::get('error') }}</div>
    @endif
    @if(\Session::has('message'))
        <div class='alert alert-success' role='alert'>{{ \Session::get('message') }}</div>
    @endif
    <br>

    {{-- ✨ 全新優化：將原本的 3 大張 Table 合併，改用具有層次感的折疊樹狀呈現 --}}
    <div class="tree-container">
        <h4 class="text-primary mb-3">🎄 營隊架構樹 (點擊可展開/折疊)</h4>
        
        @if($orgs->isEmpty())
            <p class="text-muted">目前尚未建立任何組織職務。</p>
        @else
            {{-- 這裡我們寫一個遞迴的 Blade 巨集或乾淨的區塊來渲染 --}}
            @php
                // 抓出所有根節點 (depth = 0)
                $rootOrgs = $orgs->where('prev_id', 0);
            @endphp

            @foreach($rootOrgs as $root)
                @include('..partials.org_tree_node', ['node' => $root, 'allOrgs' => $orgs, 'num_users' => $num_users])
            @endforeach
        @endif
    </div>

    {{-- 當完全沒有組織時，顯示複製與初始化區塊 --}}
    @if ($orgs->isEmpty())
        <div class="card p-4 mt-3">
            <div class="mb-3">
                <a href="{{ route('showAddOrgs', [$camp->id, 0]) }}" class="btn btn-success">✨ 初始化大會組織</a>
            </div>
            
            <form action="{{ route('copyOrgs', $camp->id) }}" method="post">
                @csrf
                <h5>🔥 複製現有營隊組織結構</h5>
                <div class="form-group row mt-3">
                    <label class="col-md-2 col-form-label text-md-right">選擇目標營隊</label>
                    <div class="col-md-6">
                        <select class='form-control' name='camp2copy' id='inputCamp2Copy' onchange='showOrgSel()'>
                            <option value=''>- 請選擇 -</option>
                            @foreach($camp_list as $item)
                                @if($item->id != $camp->id)
                                    <option value='{{$item->id}}'> [{{$item->id}}] {{$item->fullName}} </option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary btn-block">確認複製</button>
                    </div>
                </div>

                <div class="form-group row">
                    <label class="col-md-2 col-form-label text-md-right">複製選項</label>
                    <div class="col-md-10">
                        <div class="form-check form-check-inline pt-2">
                            <input class="form-check-input" type="radio" name="do_copy_permissions" id="copy_N" value="0">
                            <label class="form-check-input-label" for="copy_N">只複製組織架構</label>
                        </div>
                        <div class="form-check form-check-inline pt-2">
                            <input class="form-check-input" type="radio" name="do_copy_permissions" id="copy_Y" value="1" checked>
                            <label class="form-check-input-label" for="copy_Y">複製組織及權限關係</label>
                        </div>
                    </div>
                </div>
            </form>
            
            <div id="org2copy" class="alert alert-info mt-3" style="display:none;"></div>
        </div>
    @endif

    <script>
        function showOrgSel(){
            var camp_sel = document.getElementById("inputCamp2Copy");
            var camp_id_sel = camp_sel.options[camp_sel.selectedIndex].value;
            var org_sel = document.getElementById("org2copy");
            if(!camp_id_sel) return;

            axios.post('/semi-api/getOrgSel', { camp_id_sel: camp_id_sel })
            .then(function (response) {
                org_sel.style.display = "block";
                org_sel.innerHTML = `正在取得營隊 [${camp_id_sel}] 的組織結構，請稍候...`;
                
                let rawData = response.data;

                // 1. 安全檢查：確保資料非空
                if (!rawData || Object.keys(rawData).length === 0) {
                    org_sel.innerHTML = `⚠️ 營隊 [${camp_id_sel}] 尚未建立組織，請選擇其它營隊。`;
                    return;
                }

                // 2. 將 API 物件/陣列統一轉成 JavaScript 陣列
                const itemList = Object.values(rawData);

                // 3. 依據你的 Model 邏輯建樹 (根節點的 prev_id 為 0)
                const tree = buildOrgTree(itemList, 0);

                // 4. 渲染為視覺化的樹狀結構
                let text = `<strong>欲複製的組織結構預覽：</strong><br>`;
                text += renderOrgTreeHTML(tree);
                
                org_sel.innerHTML = text;
            })
            .catch(function (error) {
                console.error("取得組織結構失敗:", error);
                org_sel.innerHTML = `⚠️ 取得組織結構失敗，請稍後再試。`;
            });
        }

        /**
         * 搭配 CampOrg Model 的建樹函數
         * @param {Array} items - API 轉換後的陣列
         * @param {number} parentId - 上層 prev_id (根節點為 0)
         */
        function buildOrgTree(items, parentId = 0) {
            return items
                .filter(item => Number(item.prev_id) === Number(parentId))
                .map(item => ({
                    ...item,
                    children: buildOrgTree(items, item.id) // 遞迴尋找以自己 id 為 prev_id 的子節點
                }));
        }

        /**
         * 將樹狀結構繪製成簡潔的 HTML 清單
         */
        function renderOrgTreeHTML(nodes) {
            if (!nodes || nodes.length === 0) return '';
            
            let html = '<ul style="margin-top: 6px; margin-bottom: 6px; padding-left: 20px; line-height: 1.6;">';
            nodes.forEach(node => {
                html += `<li><strong>${node.position}</strong>`;
                if (node.children && node.children.length > 0) {
                    html += renderOrgTreeHTML(node.children);
                }
                html += `</li>`;
            });
            html += '</ul>';
            
            return html;
        }

        function confirmdelete(form) {
            if (confirm('⚠️ 警告：確認要刪除該職務？如果其下方有子層會一併受到影響。')) {
                form.submit();
            }
        }
    </script>
@stop
