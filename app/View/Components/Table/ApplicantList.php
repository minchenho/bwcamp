<?php

namespace App\View\Components\Table;

use Illuminate\View\Component;

class ApplicantList extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(
        public array|null $columns,
        public mixed $applicants, // 💡 關鍵修改：將 Collection|null 改為 mixed，相容分頁器與集合
        public bool $isSetting = false,
        public bool $isShowVolunteers = false,
        public bool $isShowLearners = false,
        public $registeredVolunteers = null, // 💡 順手修正：若此處也可能因分頁改變，建議一併改為 mixed 或移除型別
        public $isSettingCarer = null
    ) { }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.table.applicant-list');
    }
}