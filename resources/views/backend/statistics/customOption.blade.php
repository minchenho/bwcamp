@extends('backend.master')
@section('content')
<div style="display: flex; flex-direction: column; align-items: flex-start; gap: 10px; margin-bottom: 20px;">
    <h2 style="margin: 0;">{{ $camp->abbreviation }} 統計資料：{{ $title1 }}</h2>
    
    <div class="form-inline">
        <label for="view_mode" style="margin-right: 10px; font-weight: bold;">統計模式：</label>
        <select id="view_mode" class="form-control" style="padding: 6px 12px; font-size: 14px;">
            <option value="single">選項一：全區統計 (僅顯示全區)</option>
            <option value="multi">選項二：分區統計 (同時顯示各分區及其它)</option>
        </select>
    </div>
</div>
    
    <div id="charts_container_wrapper"></div>

    <script type='text/javascript'>
        // 1. 接收後端處理好的完整資料集與分區清單
        var chartCollection = {!! $chartCollectionJson !!};
        var specifiedKeys = {!! json_encode($specifiedKeys) !!};

        // 2. 註冊 Google Chart 載入完成的 Hook
        google.charts.setOnLoadCallback(function() {
            // 預設進來顯示選項一（全區）
            renderChartsByMode('single');
        });

        // 3. 監聽模式選單切換
        document.getElementById('view_mode').addEventListener('change', function() {
            renderChartsByMode(this.value);
        });

        // 4. 核心渲染分流函式
        function renderChartsByMode(mode) {
            var keysToRender = [];
            
            if (mode === 'single') {
                keysToRender = ['all'];
            } else if (mode === 'multi') {
                keysToRender = [...specifiedKeys, 'other'];
            }

            var wrapper = document.getElementById('charts_container_wrapper');
            wrapper.innerHTML = ''; // 清空舊圖表

            // 5. 循環開始繪製畫面上需要的每一組的圖表
            keysToRender.forEach(function(key) {
                var targetGroup = chartCollection[key];
                if (!targetGroup) return;

                var pieId = 'piechart_' + key;
                var barId = 'barchart_' + key;

                // 核心：建立一個群組區塊，並使用 flex-wrap: nowrap 與 gap 實現完美並排不換行
                var groupBlock = document.createElement('div');
                groupBlock.style.marginBottom = "35px"; 
                groupBlock.innerHTML = `
                    <h3 style="margin: 0 0 10px 5px; font-weight: bold; color: #333;">${targetGroup.label} 統計</h3>
                    <div style="display: flex; flex-wrap: nowrap; gap: 15px;">
                        <div id="${pieId}" style="border: 1px solid #ccc; width: 450px; height: 500px;"></div>
                        <div id="${barId}" style="border: 1px solid #ccc; width: 450px; height: 500px;"></div>
                    </div>
                `;
                wrapper.appendChild(groupBlock);

                // 無資料防錯
                if (!targetGroup.data.rows || targetGroup.data.rows.length === 0) {
                    var noDataHtml = '<div style="text-align:center; line-height:500px; color:#999; font-size:16px;">' + targetGroup.label + '暫無資料</div>';
                    document.getElementById(pieId).innerHTML = noDataHtml;
                    document.getElementById(barId).style.display = 'none'; 
                    return; 
                }

                // 6. 載入資料並繪製 Google Chart
                var data = new google.visualization.DataTable(targetGroup.data);
        
                var chart_options = {
                    'title': targetGroup.label + '{{ $title1 }}' + '，共 ' + targetGroup.total + ' 人',
                    'hAxis': {
                        'title': '報名人數',
                        'titleTextStyle': {'bold': true, 'italic': false},
                    },
                    'vAxis': {
                        'title': '{{ $title1 }}',
                        'titleTextStyle': {'bold': true, 'italic': false},
                    },
                    'width': 450,
                    'height': 500,
                    'legend': {'position': 'none'},
                    'annotations': {'alwaysOutside': true},
                    'pieSliceText': 'label'
                };
        
                var piechart = new google.visualization.PieChart(document.getElementById(pieId));
                piechart.draw(data, chart_options);
        
                var barchart = new google.visualization.BarChart(document.getElementById(barId));
                barchart.draw(data, chart_options);
            });
        }
    </script>
@endsection