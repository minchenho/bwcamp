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
    
    <table class='columns' id="charts_container_table">
    </table>

    <script type='text/javascript'>
        // 1. 接收後端處理好的分區資料集與指定的 Keys
        var chartCollection = {!! $chartCollectionJson !!};
        var specifiedKeys = {!! json_encode($specifiedKeys) !!}; 
        var axisTitle = "{{ $title1 }}"; // 動態作為圖表 Y 軸的標題名

        // 2. 註冊 Google Chart 載入完成的 Hook
        google.charts.setOnLoadCallback(function() {
            renderChartsByMode('single'); // 預設全區
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

            var containerTable = document.getElementById('charts_container_table');
            containerTable.innerHTML = ''; // 清空舊圖表

            // 5. 循環繪製每一列的 Pie 與 Bar 圖表
            keysToRender.forEach(function(key) {
                var targetGroup = chartCollection[key];
                if (!targetGroup) return; 

                var pieId = 'piechart_' + key;
                var barId = 'barchart_' + key;

                var tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="padding: 15px 10px 5px 0;">
                        <div id="${pieId}" style="border: 1px solid #ccc; width: 450px; height: 500px;"></div>
                    </td>
                    <td style="padding: 15px 0 5px 10px;">
                        <div id="${barId}" style="border: 1px solid #ccc; width: 450px; height: 500px;"></div>
                    </td>
                `;
                containerTable.appendChild(tr);

                // 無資料防錯
                if (!targetGroup.data.rows || targetGroup.data.rows.length === 0) {
                    var noDataHtml = '<div style="text-align:center; line-height:500px; color:#999; font-size:16px;">' + targetGroup.label + '暫無資料</div>';
                    document.getElementById(pieId).innerHTML = noDataHtml;
                    document.getElementById(barId).innerHTML = noDataHtml;
                    return; 
                }

                var data = new google.visualization.DataTable(targetGroup.data);
        
                var chart_options = {
                    'title': targetGroup.label + axisTitle + '統計，共 ' + targetGroup.total + ' 人',
                    'hAxis': {
                        'title': '報名人數',
                        'titleTextStyle': {'bold': true, 'italic': false},
                    },
                    'vAxis': {
                        'title': axisTitle, // 💡 動態軸標籤
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