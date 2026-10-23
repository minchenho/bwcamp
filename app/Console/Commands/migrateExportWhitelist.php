<?php

namespace App\Console\Commands;

use App\Models\Camp;
use App\Models\CampOrg;
use App\Models\User;
use App\Services\CampManageAccessService;
use App\Services\CampOrgService;
use Illuminate\Console\Command;

class migrateExportWhitelist extends Command
{
    /**
     * 一次性轉換：將 .env 匯出白名單（config/special_permissions.php）轉為職務的 export 權限
     * 預設只列出將做的變更，加上 --apply 才會寫入
     *
     * @var string
     */
    protected $signature = 'permission:migrateExportWhitelist {--apply}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '將 .env 匯出白名單轉為職務的匯出(export)權限';

    // 白名單 => 學員營隊類型（權限都掛在學員營隊上）
    protected $whitelists = [
        'ceo_emails' => 'ceocamp',
        'ecamp_emails' => 'ecamp',
        'utcamp_emails' => 'utcamp',
        'ycamp_emails' => 'ycamp',
    ];

    protected $resources = [
        '\App\Models\Applicant' => '學員',
        '\App\Models\Volunteer' => '義工',
    ];

    public function handle(CampOrgService $campOrgService)
    {
        $apply = $this->option('apply');
        $rows = [];
        $toGrant = [];

        foreach ($this->whitelists as $key => $table) {
            $campIds = Camp::where('table', $table)->pluck('id');
            foreach (config('special_permissions.' . $key, []) as $email) {
                $user = User::where('email', $email)->first();
                if (!$user) {
                    $rows[] = [$table, $email, '-', '-', '略過：查無此使用者'];
                    continue;
                }
                $orgs = CampOrg::with('camp', 'permissions')
                    ->whereIn('camp_id', $campIds)
                    ->whereHas('users', fn ($query) => $query->where('users.id', $user->id))
                    ->get();
                if ($orgs->isEmpty()) {
                    $rows[] = [$table, $email, '-', '-', '略過：在此類營隊沒有職務'];
                    continue;
                }
                foreach ($orgs as $org) {
                    $orgName = $org->camp?->abbreviation . ' / ' . $org->position . " (#{$org->id}，{$org->users()->count()} 人)";
                    foreach ($this->resources as $resource => $resourceName) {
                        $permissions = $org->permissions->where('resource', $resource);
                        if ($permissions->where('action', 'export')->isNotEmpty()) {
                            $rows[] = [$table, $email, $orgName, $resourceName, '已有匯出權限'];
                            continue;
                        }
                        $range = $permissions->firstWhere('action', 'read')?->range;
                        if (!in_array($range, CampManageAccessService::EXPORT_RANGES)) {
                            $rows[] = [$table, $email, $orgName, $resourceName, '略過：瀏覽範圍為 ' . ($range ?? '無瀏覽權限')];
                            continue;
                        }
                        $rows[] = [$table, $email, $orgName, $resourceName, ($apply ? '已新增' : '將新增') . "匯出權限（範圍 {$range}）"];
                        $toGrant["{$org->id}|{$resource}"] = [$org, $resource, $range];
                    }
                }
            }
        }

        $this->table(['營隊類型', 'Email', '職務', '資料', '結果'], $rows);

        if (!$apply) {
            $this->info('以上為預覽，確認無誤後加上 --apply 寫入。');
            return 0;
        }
        foreach ($toGrant as [$org, $resource, $range]) {
            $permission = $campOrgService->findOrCreatePermission($org, $resource, 'export', $range);
            $org->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $this->info('已新增 ' . count($toGrant) . ' 筆職務匯出權限。');
        return 0;
    }
}
