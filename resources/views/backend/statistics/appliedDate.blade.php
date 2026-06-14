@extends('backend.master')
@section('content')
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>{{ $campFullData->abbreviation }} 統計資料：報名日期</h2>
        
        <div class="form-inline">
            <label for="view_mode" style="margin-right: 10px; font-weight: bold;">統計模式：</label>
            <select id="view_mode" class="form-control" style="padding: 6px 12px; font-size: 14px;">
                <option value="single">選項一：全區統計 (僅顯示全區)</option>
                <option value="multi">選項二：分區統計 (同時顯示各分區及其它)</option>
            </select>
        </div>
    </div>
    
    <div id="charts_wrapper">
        </div>

    <script type='text/javascript'>
        // 1. 接收後端處理好的完整資料集與分區清單
        var chartCollection = {!! $chartCollectionJson !!};
        var specifiedKeys = {!! json_encode($specifiedKeys) !!};

        // 2. 註冊 Google Chart 載入完成的 Hook
        google.charts.setOnLoadCallback(function() {
            // 預設載入選項一（全區）
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

            var wrapper = document.getElementById('charts_wrapper');
            wrapper.innerHTML = ''; // 清空舊圖表節點

            // 5. 循環繪製每一組分區的兩個圖表
            keysToRender.forEach(function(key) {
                var targetGroup = chartCollection[key];
                if (!targetGroup) return;

                var dailyDivId = 'chart_daily_' + key;
                var accuDivId  = 'chart_accu_' + key;

                // 建立該分區獨立的區塊結構
                var groupBlock = document.createElement('div');
                groupBlock.style.marginBottom = "40px";
                groupBlock.innerHTML = `
                    <div id="${dailyDivId}"></div>
                    <div id="${accuDivId}"></div>
                `;
                wrapper.appendChild(groupBlock);

                // 無資料防錯
                if (!targetGroup.daily.rows || targetGroup.daily.rows.length === 0) {
                    var noDataHtml = '<div style="width:1000px; height:150px; text-align:center; line-height:150px; color:#999; border:1px solid #ccc; margin-bottom:10px; font-size:16px;">' + targetGroup.label + '暫無報名日期資料</div>';
                    document.getElementById(dailyDivId).innerHTML = noDataHtml;
                    document.getElementById(accuDivId).style.display = 'none'; // 隱藏累計圖容器
                    return; 
                }

                // 6. 建立每日新增圖表
                var dataDaily = new google.visualization.DataTable(targetGroup.daily);
                var optionsDaily = {
                    'title': targetGroup.label + '報名日期統計，共 ' + targetGroup.total + ' 人',
                    'hAxis': {
                        'title': '報名日期',
                        'titleTextStyle': {'bold': true, 'italic': false},
                        'format': 'M/d'
                    },
                    'vAxis': {
                        'title': '報名人數',
                        'titleTextStyle': {'bold': true, 'italic': false},
                        'format': 'decimal'
                    },
                    'width': 1000,
                    'height': 400,
                    'legend': {'position': 'none'},
                    'annotations': {'alwaysOutside': true}
                };
                var chartDaily = new google.visualization.ColumnChart(document.getElementById(dailyDivId));
                chartDaily.draw(dataDaily, optionsDaily);

                // 7. 建立每日累計圖表
                var dataAccu = new google.visualization.DataTable(targetGroup.accu);
                var optionsAccu = {
                    'title': targetGroup.label + '報名日期累計統計，共 ' + targetGroup.total + ' 人',
                    'hAxis': {
                        'title': '報名日期',
                        'titleTextStyle': {'bold': true, 'italic': false},
                        'format': 'M/d'
                    },
                    'vAxis': {
                        'title': '累計人數',
                        'titleTextStyle': {'bold': true, 'italic': false},
                        'format': 'decimal'
                    },
                    'width': 1000,
                    'height': 400,
                    'legend': {'position': 'none'},
                    'annotations': {'alwaysOutside': true}
                };
                var chartAccu = new google.visualization.ColumnChart(document.getElementById(accuDivId));
                chartAccu.draw(dataAccu, optionsAccu);
            });
        }
    </script>
@endsection