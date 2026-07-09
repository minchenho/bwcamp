<div class="mt-2">
    @if ($queryStr ?? false) 查詢條件：{{ $queryStr }} @endif
    <!-- Nothing worth having comes easy. - Theodore Roosevelt -->
    <div class="wrapper1">
        <div class="div1"></div>
    </div>
    <div class="text-danger mt-3">
        已取消報名： {{ $applicants->whereNotNull("deleted_at")->count() }} 人
    </div>
    <table class="table table-bordered table-hover"
{{--        style="overflow-x: auto;"--}}
        id="applicantTable"
        data-show-columns="true"
        data-show-columns-search="true"
        data-search="true"
        data-search-highlight="true"
        data-search-align="left"
        data-pagination="true"
        data-smart-display="false"
        data-pagination-loop="false"
        data-pagination-v-align="both"
{{--        data-show-export="true"--}}
{{--        data-export-data-type="all"--}}
        data-page-list="[10, 50, 100]"
        data-pagination-pre-text="上一頁"
        data-pagination-next-text="下一頁"
        data-row-style="rowStyle">
        <caption></caption>
        <thead id="applicantTableHead">
            <tr class="bg-success text-white">
                @if(($isSetting ?? false) || ($isSettingCarer ?? false))
                    <th class="text-center" data-field="checkfield"></th>
                @endif
                @foreach ($columns ?? [] as $key => $item)
                    @if($isSettingCarer && ($key == 'mobile' || $key == 'email' || $key == 'zipcode' || $key == 'address' || $key == 'birthdate' || $key == 'after_camp_available_day' || $key == 'region'))
                    @elseif($isSettingCarer && ($key == 'participation_mode'))
                        <th class="text-center" data-field="is_attend" data-sortable="1">參加意願</th>
                        <th class="text-center" data-field="participation_mode" data-sortable="1">參加形式</th>
                    @elseif($key == "industry" && $isSettingCarer)
                        <th class="text-center" data-field="introducer" data-sortable="0">推薦人</th>
                        <th class="text-center" data-field="carer" data-sortable="1">關懷員</th>
                    @elseif($key == "carer" && $isSettingCarer)
                        @continue
                    @elseif(!$isShowVolunteers && !$isShowLearners && $key == "contactlog")
                    @elseif($key == "contactLog" && $currentUser->canAccessResource(new App\Models\ContactLog(), 'read', $campFullData))
                        <th class="text-center" data-field="contactLog" data-sortable="0">關懷記錄</th>
                    @elseif($key == "gender")
                        <th class="text-center" data-field="gender_chn" data-sortable="{{ $item['sort'] }}">{{ $item['name'] }}</th>
                    @elseif($key == "is_attend")
                        <th class="text-center" data-field="is_attend_chn" data-sortable="{{ $item['sort'] }}">{{ $item['name'] }}</th>
                    @else
                        <th class="text-center" data-field="{{ $key }}" data-sortable="{{ $item['sort'] }}">{{ $item['name'] }}</th>
                    @endif
                    <th data-field="deleted_at" data-visible="false" data-sortable="0"></th>
                @endforeach
            </tr>
        </thead>
    </table>
</div>

<script>
    // 1. 全域環境變數設定
    window.applicant_ids = [];
    window.csrf_token = "{{ csrf_token() }}";
    window.columns = @json($columns);
    window.isShowLearners = {{ $isShowLearners ? 1 : 0 }};
    window.isShowVolunteers = {{ $isShowVolunteers ? 1 : 0 }};
    
    // 2. 原始資料注入（💡 使用 items() 修正全白衝突，並將後端 load 邏輯回歸 Controller）
    let only_applicants = @json($applicants->items());
    let user_application_logs = @json($users_applicants ?? []);

    // 3. 滾動軸與分頁初始化（💡 移除重複監聽，整併為一個乾淨的邏輯）
    $(function() {
        // 雙滾動軸同步
        $(".wrapper1").scroll(function(){
            $(".fixed-table-body").scrollLeft($(".wrapper1").scrollLeft());
        });
        $(".fixed-table-body").scroll(function(){
            $(".wrapper1").scrollLeft($(".fixed-table-body").scrollLeft());
        });

        // 填充資料並初始化表格
        fillTheList();

        // 💡 修正重複監聽：全站只保留這一個換頁勾選記憶，使用 sleep 確保前端渲染完才去勾
        $('#applicantTable').on('page-change.bs.table', function (number, size) {
            sleep(50).then(() => {
                $('.applicants_selector').each(function () {
                    $.inArray('A' + this.value, window.applicant_ids) === -1 ? $(this).prop('checked', false) : $(this).prop('checked', true);
                });
            });
        });
    });

    // 4. 行內樣式判斷
    window.rowStyle = (row, index) => {
        if (row.deleted_at) {
            return { css: { 'color': 'rgba(120, 120, 120, 0.4)' } };
        }
        return { css: { color: '' } };
    }

    function sleep (time) {
        return new Promise((resolve) => setTimeout(resolve, time));
    }

    function applicant_triggered(id) {
        if ($("#" + id).is(":checked")) {
            window.applicant_ids.push(id);
        } else {
            window.applicant_ids = window.applicant_ids.filter(val => val != id);
        }
    }

    // 5. 資料加工與渲染（💡 補上你最需要的 applied_at 日期格式化邏輯）
    function fillTheList() {
        let table = $('#applicantTable');
        let data = null;
        let applicants_array = null;
        
        if (user_application_logs.length > 0) {
            applicants_array = Object.values(only_applicants);
            data = user_application_logs.concat(applicants_array);
        } else {
            data = typeof only_applicants.length == "undefined" ? [only_applicants] : only_applicants;
        }
        
        var result = Object.values(data).filter(item => item != null && item != 0);
        if (result.length == 1 && !result[0].id) {
            result = Object.values(result[0]);
        }

        result.forEach(function(item) {
            item.gender = item.gender_chn;
            item.is_attend = item.is_attend_chn;
            item.batch = !item.batch ? "沒有梯次資料" : item.batch.name;
            
            if (item.group_relation) {
                item.group = item.group_relation.alias;
            }
            
            // 處理生日
            if (item.birthday || item.birthmonth || item.birthyear) {
                const formatBirthdate = (year, month, day) => {
                    const parts = [];
                    if (year) parts.push(year + '年');
                    if (month) parts.push(month + '月');
                    if (day) parts.push(day + '日');
                    return parts.join('');
                };
                item.birthdate = formatBirthdate(item.birthyear, item.birthmonth, item.birthday);
            }

            // 💡 新增：在這裡修改你最需要的「報名時間（created_at）」印出格式
            if (item.created_at) {
                let dateObj = new Date(item.created_at);
                if (!isNaN(dateObj.getTime())) {
                    let yyyy = dateObj.getFullYear();
                    let mm = String(dateObj.getMonth() + 1).padStart(2, '0');
                    let dd = String(dateObj.getDate()).padStart(2, '0');
                    let hh = String(dateObj.getHours()).padStart(2, '0');
                    let min = String(dateObj.getMinutes()).padStart(2, '0');
                    item.created_at = `${yyyy}-${mm}-${dd} ${hh}:${min}`; // 格式：2026-07-09 15:30
                }
            }
            if (item.admitted_at) {
                let dateObj = new Date(item.admitted_at);
                if (!isNaN(dateObj.getTime())) {
                    let yyyy = dateObj.getFullYear();
                    let mm = String(dateObj.getMonth() + 1).padStart(2, '0');
                    let dd = String(dateObj.getDate()).padStart(2, '0');
                    let hh = String(dateObj.getHours()).padStart(2, '0');
                    let min = String(dateObj.getMinutes()).padStart(2, '0');
                    item.admitted_at = `${yyyy}-${mm}-${dd} ${hh}:${min}`; // 格式：2026-07-09 15:30
                }
            }

            item.name_original = item.name;
            let targetCampId = window.isShowVolunteers ? "{{ $campFullData->vcamp?->id }}" : "{{ $campFullData->id }}";
            
            if (item.user) {
                item.name = '<a href="{{ route("showAttendeeInfoGET", "") }}/' + targetCampId + '?snORadmittedSN=' + item.id + '&openExternalBrowser=1" target="_blank" class="text-primary">' + item.name + '</a>&nbsp;(報名序號：' + item.id + ')<div class="text-success">連結之帳號：' + item.user.name + '(' + item.user.email + ')</div>';
            } else {
                item.name = '<a href="{{ route("showAttendeeInfoGET", "") }}/' + targetCampId + '?snORadmittedSN=' + item.id + '&openExternalBrowser=1" target="_blank" class="text-primary">' + item.name + '</a>&nbsp;(報名序號：' + item.id + ')';
            }
            
            item.contactlog = item.contactlogHTML;
            item.avatar = '<img src="{{ url("/backend/") }}/' + "{{ $campFullData->id }}" + '/avatar/' + item.id + '" width=80 alt="' + item.name_original + '">';
            item.carer = item.carers?.length ? item.carers.map(c => c.name).join('<br>') : null;

            @if(($isSetting ?? false) || ($isSettingCarer ?? false))
                item.checkfield = '<input type="checkbox" name="applicants[]" class="applicants_selector" value="' + item.id + '" id="A' + item.id + '" onclick="applicant_triggered(this.id)">';
            @endif
        });

        try {
            table.bootstrapTable({data: result});
        } catch (e) {
            console.log(e);
        }
    }
</script>

<style>
    .wrapper1{width: 400px; border: none 0px RED;
        overflow-x: scroll; overflow-y:hidden;}
    .wrapper1{height: 20px; }
    .div1 {width:600px; height: 20px; }
    .fixed-table-body { height: auto !important; }
</style>
