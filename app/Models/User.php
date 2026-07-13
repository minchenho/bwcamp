<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Laratrust\Traits\LaratrustUserTrait;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Traits\EmailConfiguration;

class User extends Authenticatable
{
    use Notifiable;
    use EmailConfiguration;
    use LaratrustUserTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token', 'permission',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected $camp_permissions = [];

    protected $rolePermissions = null;

    protected $camp_roles = [];

    public $resourceNameInMandarin = '義工資料';

    public $resourceDescriptionInMandarin = '義工帳號的資料，非必要請勿使用這個權限。';

    private static $permissions;

    private static $forInspect;

    private static $batchesForPermissionInspection;

    public function __construct(array $attributes = [])
    {
        $this->bootIfNotBooted();
        $this->initializeTraits();
        $this->syncOriginal();
        $this->fill($attributes);
        $this->camp_permissions = collect($this->camp_permissions);
        $this->camp_roles = collect($this->camp_roles);
    }
    
    // roles() 這裡沒有定義
    // 要看 use Laratrust\Traits\LaratrustUserTrait;
    // 以及 config/laratrust.php 裡面定義的 table
    // 其實就是去抓出 camp_orgs
    
    public function legace_roles()
    {
        return $this->belongsToMany('App\Models\Role', 'role_user', 'user_id', 'role_id');
    }

    /**
     * 公開正門：允許外面的 Model (如 Applicant) 安全獲取成型後的營隊權限
     */

    public function getCampRoles($camp)
    {
        // 檢查目前的 roles 裡面，有沒有任何一條符合當前營隊的 camp_id？
        $hasCurrentCampRoles = collect($this->camp_roles)
            ->contains('camp_id', $camp->id);

        // 如果沒有, 強制啟動 Parser，強制更新 camp_roles 和 camp_permissions
        if (!$hasCurrentCampRoles) {
            $this->permissionsRolesParser($camp);
        }
        return $this->camp_roles;
    }

    public function getCampPermissions($camp)
    {
        // 看看目前裝著的權限裡面，有沒有任何一條的 camp_id 符合現在要查的營隊？
        $hasCurrentCampPermissions = collect($this->camp_permissions)
            ->contains('camp_id', $camp->id);

        // 如果沒有，啟動 Parser 更新
        if (!$hasCurrentCampPermissions) {
            $this->permissionsRolesParser($camp);
        }
        return $this->camp_permissions;
    }

    public function getAccessibleSections($camp)
    {
        //*** sections (大組): 是 depth = 1 的職務
        $myRoles = collect($this->getCampRoles($camp));

        if ($myRoles->isEmpty()) {
            return collect();
        }

        // 如果是超級管理員
        if ($this->isSuperuser) {
            return \App\Models\CampOrg::where('camp_id', $camp->id)->where('depth', 1)->get();
        }

        // 😎 乾淨流暢的 Collection 連鎖技：
        return $myRoles
            ->map(fn($role) => $role->closest_section) // 👈 叫每個職務去啟動自帶的 closest_section 屬性
            ->filter()                                 // 剔除找不到大組的邊界狀況
            ->unique('id')                             // 聯集去重
            ->values();                                // 重新整理索引
    }

    public function getPermission($top = false, $camp_id = null, $function_id = null)
    {
        if (!$top) {
            $hasRole = \App\Models\RoleUser::join('roles', 'roles.id', '=', 'role_user.role_id')->where('user_id', $this->id)->orderBy('level', 'asc')->get();
            if ($hasRole->count() == 0) {
                $empty = new \App\Models\Role();
                $empty->level = 999;
                return $empty;
            }
            return $hasRole->first();
        } elseif ($top) {
            if ($camp_id) {
                return \DB::table('roles')->where('camp_id', $camp_id)->whereIn('id', $this->legace_roles()->pluck('role_id'))->orderBy('level', 'desc')->first();
            } else {
                return \DB::table('roles')->whereIn('id', $this->legace_roles()->pluck('role_id'))->orderBy('level', 'desc')->get();
            }
        }
    }

    // $user->isSuperuser or $user->is_superuser 都可以
    public function getIsSuperuserAttribute(): bool
    {
        return $this->permission === 'superuser';
    }

    public function groupOrgRelation()
    {
        return $this->belongsToMany(CampOrg::class, 'org_user', 'user_id', 'org_id');
    }

    /**
     * Send the given notification.
     *
     * @param  mixed  $instance
     * @return void
     */
    public function notify($instance)
    {
        $this->setEmail($this->role_relations->first()->role->camp->table ?? "");
        app(\Illuminate\Contracts\Notifications\Dispatcher::class)->send($this, $instance);
    }

    public function caresLearners()
    {
        return $this->belongsToMany(Applicant::class, CarerApplicantXref::class, 'user_id', 'applicant_id', 'id', 'id');
    }

    public function application_log()
    {
        return $this->belongsToMany(Applicant::class, UserApplicantXref::class, 'user_id', 'applicant_id', 'id', 'id');
    }

    public function canAccessResult()
    {
        return $this->hasMany(Ucaronr::class);
    }

    // protected function canAccessResourceLocal(): Attribute
    // {
    //     return Attribute::make(
    //         get: fn (string $value) => ucfirst($value),
    //     );
    // }

    public function applicants($camp)
    {
        //找到相關的
        if ($camp->isVamp) {
            $vbatch_id = $camp->batches->pluck('id');
        } else {
            $vbatch_id = $camp->vcamp->batches->pluck('id');
        }
        $applicants_all = $this->application_log;
        $applicants_filtered = $applicants_all->whereIn('batch_id', $vbatch_id);
        return $applicants_filtered;
    }
    public function permissionsRolesParser($camp)
    {
        /**
         *  1. 取得該義工於營隊內的所有職務
         *  2. 取出所有權限的聯集，並以條列方式呈現
         */

        // 1. 利用 with('permissions') 做到 Eager Loading（預載入）
        // 不論身兼幾個職務，永遠只跑「2 次 SQL」，效能直接起飛！
        $rolesWithPermissions = $this->roles()
            ->where('camp_id', $camp->id)
            ->with('permissions') 
            ->get();

        // 2. 取出所有權限的聯集，因為preload，下面（filter, map）通通在記憶體完成！
        $permissions = $rolesWithPermissions
                        ->filter(static fn ($role) => $role->permissions->count() > 0)
                        ->map(static fn ($role) => $role->permissions)
                        ->flatten()->unique('id')->values();

        $permissions = $permissions->sortBy(["resource", "action"]);
        $parsed = collect();    //$parsed 只是陣列

        $trueWeights = [
            "person" => 1,                 // 個人最弱
            "learner_group" => 2,          // 小組第二
            "volunteer_large_group" => 3,  // 大組第三
            "all" => 4,                    // 全部(小組)
            "na" => 5,                     // 不指定
        ];

        $permissions->each(function ($permission) use (&$parsed, $trueWeights) {
            // 在迴圈剛開始時，先定義出目前這筆權限的權重！
            $currentWeight = $trueWeights[$permission->range] ?? 0;
            // 1. 改成用 search 找出這筆舊權限在 $parsed 籃子裡的「陣列索引 (0, 1, 2...)」
            $existingIndex = $parsed->search(function ($item) use ($permission) {
                return $item['resource'] === $permission->resource && $item['action'] === $permission->action;
            });

            // 2. 如果找得到舊權限 (search 找不到會回傳 false)
            if ($existingIndex !== false) {
                // 拿到本尊的資料
                $existing = $parsed->get($existingIndex);
                $existingWeight = $trueWeights[$existing["range"]] ?? 0;
                
                if ($existingWeight < $currentWeight) {
                    // ✨ 3. 核心大招：直接用 $parsed[$existingIndex] 鎖定籃子裡的本尊進行修改！
                    // range_parsed 必須要同步回填，因為外層的 switch 正在嗷嗷待哺等著這個數字！
                    $parsed[$existingIndex] = [
                        "resource"     => $permission->resource,
                        "action"       => $permission->action,
                        "description"  => $permission->description,
                        "range"        => $permission->range,
                        "range_parsed" => $permission->range_parsed,
                    ];
                }
            } else {
                // 第一次見面，直接塞進籃子
                $parsed->push([
                    "resource"     => $permission->resource,
                    "action"       => $permission->action,
                    "description"  => $permission->description,
                    "range"        => $permission->range,
                    "range_parsed" => $permission->range_parsed,
                ]);
            }
        });
        $this->camp_permissions = $parsed;
        $this->camp_roles = $rolesWithPermissions;
        return $parsed;
    }

    public function canAccessResource($resource, $action, $camp, $context = null, $target = null, $probing = null)
    {
        if (!$resource) {
            return false;
        }

        /*MCH: 好像沒用到
        $class = is_string($resource) ? $resource : get_class($resource);
        if ($resource instanceof \App\Models\Volunteer && $context == "vcampExport") {
            $class = "App\\Models\\Applicant";
        }

        $batch_id = null;
        $region_id = null;
        if ($resource instanceof \App\Models\Applicant || $resource instanceof \App\Models\Volunteer) {
            $batch_id = $resource->batch_id;
            $region_id = $resource->region_id;
        } elseif ($resource instanceof \App\Models\User) {
            $theCamp = $camp->vcamp;
            if (!self::$batchesForPermissionInspection) {
                self::$batchesForPermissionInspection = $theCamp->batches()->get();
            }
            $theApplicant = $resource->application_log->whereIn('batch_id', self::$batchesForPermissionInspection->pluck('id'))->first();
            $batch_id = $theApplicant?->batch_id;
            $region_id = $theApplicant?->region_id;
        }
        */

        // $existingAccessResult = $this->canAccessResult()
        //     ->where('camp_id', $camp->id)
        //     ->where('batch_id', $batch_id)
        //     ->where('region_id', $region_id)
        //     ->where('accessible_id', $target->id ?? null)
        //     ->where('accessible_type', $class)
        //     ->first();

        // if ($existingAccessResult) {
        //     return $existingAccessResult->can_access;
        // } else {
        //     return $this->fillingAccessibleResult($resource, $action, $camp, $context, $target, $probing);
        // }

        return $this->getAccessibleResult($resource, $action, $camp, $context, $target, $probing);
    }

    /**
     * 批次檢查多個資源的存取權限
     *
     * @param Collection $resources 要檢查的資源集合
     * @param string $action 動作（read, create, update, delete）
     * @param Camp $camp 營隊
     * @param string|null $context 上下文
     * @return Collection 回傳包含 resource_id => bool 的集合
     */
    public function batchCanAccessResources($resources, $action, $camp, $context = null)
    {
        if ($resources->isEmpty()) {
            return collect();
        }

        // 使用原本的單一檢查邏輯，但批次處理以減少查詢
        // 預載入所有需要的資料
        $this->loadMissing(['roles' => function ($query) use ($camp) {
            $query->where('camp_id', $camp->id)->with('permissions');
        }]);

        // 預載入 camp_roles
        if (!$this->camp_roles) {
            $this->camp_roles = $this->permissionsRolesParser($camp);
        }

        // 預載入關懷學員資料（如果需要）
        $resourceClass = get_class($resources->first());
        if ($resourceClass === 'App\Models\Applicant') {
            $this->load(['caresLearners' => function ($query) use ($camp) {
                $query->whereIn('batch_id', $camp->batches->pluck('id'));
            }]);
        }

        // 批次預載入資源的相關資料
        if ($resourceClass === 'App\Models\Applicant' || $resourceClass === 'App\Models\Volunteer') {
            if ($resourceClass === 'App\Models\Volunteer') {
                $resources->load('user.roles');
            }
        }

        // 使用原本的邏輯逐一檢查，但利用預載入的資料來提升效能
        return $resources->mapWithKeys(function ($resource) use ($action, $camp, $context) {
            // 直接使用原本的 canAccessResource 邏輯，但因為資料已經預載入，所以會快很多
            $canAccess = $this->getAccessibleResult($resource, $action, $camp, $context, $resource);
            return [$resource->id => $canAccess];
        });
    }

    public function fillingAccessibleResult($resource, $action, $camp, $context = null, $target = null, $probing = null)
    {
        $result = $this->getAccessibleResult($resource, $action, $camp, $context, $target, $probing);
        $class = is_string($resource) ? $resource : get_class($resource);
        $batch_id = null;
        $region_id = null;
        if ($resource instanceof \App\Models\Applicant || $resource instanceof \App\Models\Volunteer) {
            $batch_id = $resource->batch_id;
            $region_id = $resource->region_id;
        } elseif ($resource instanceof \App\Models\User) {
            $theCamp = $camp->vcamp;
            $theApplicant = $resource->application_log->whereIn('batch_id', $theCamp->batches()->pluck('id'))->first();
            $batch_id = $theApplicant?->batch_id;
            $region_id = $theApplicant?->region_id;
        }
        $this->canAccessResult()->firstOrCreate([
            'user_id' => $this->id,
            'camp_id' => $camp->id,
            'batch_id' => $batch_id,
            'region_id' => $region_id,
            'accessible_id' => $target->id ?? null,
            'accessible_type' => $class,
            'context' => $context,
            'can_access' => $result ? 1 : 0
        ]);
        return $result ? true : false;
    }

    public function getAccessibleResult($resource, $action, $camp, $context = null, $target = null, $probing = null)
    {
        // 👍 完美防禦：只要發現沒載入過，就直接呼叫 parser 
        if (is_null($this->camp_roles) || $this->camp_roles->isEmpty()) {
            //permissionRolesParser() will update 
            //$this->camp_roles and $this->camp_permissions               
            $this->permissionsRolesParser($camp);
        }

        if (!$resource) {
            return false;
        }
        if ($context == "vcampExport" && $target) {
            $camp = Vcamp::query()->find($target->camp->id)->mainCamp;
        }
        $class = is_string($resource) ? $resource : get_class($resource);

        if ($resource instanceof \App\Models\Volunteer && $context == "vcampExport") {
            $class = "App\\Models\\Applicant";
        }

        // 營隊權限
        // $this->rolePermissions = $this->roles()->where('camp_id', $camp->id)->get()
        //                 ->filter(static fn($role) => $role->permissions->count() > 0)
        //                 ->map(static fn($role) => $role->permissions)
        //                 ->flatten()->unique('id')->values();
        $constraint = function ($query) use ($camp, $resource) {
            $query->where(function ($query) use ($resource, $camp) {
                // 順便做梯次檢查
                if ($resource instanceof \App\Models\Applicant || $resource instanceof \App\Models\Volunteer) {
                    if ($resource->batch_id) {
                        $query->where(function ($query) use ($resource) {
                            $query->where(function ($query) {
                                $query->whereNull('batch_id');
                            })->orWhere(function ($query) use ($resource) {
                                $query->where('batch_id', $resource->batch_id);
                            });
                        });
                    }
                } elseif ($resource instanceof \App\Models\User) {
                    $theCamp = $camp->vcamp;
                    $theApplicant = $resource->application_log->whereIn('batch_id', $theCamp?->batches()->pluck('id'))->first();
                    if ($theApplicant) {
                        $query->where(function ($query) use ($theApplicant) {
                            $query->where(function ($query) {
                                $query->whereNull('batch_id');
                            })->orWhere(function ($query) use ($theApplicant) {
                                $query->where('batch_id', $theApplicant->batch_id);
                            });
                        });
                    }
                }
                // 區域檢查
                if ($resource instanceof \App\Models\Applicant || $resource instanceof \App\Models\Volunteer) {
                    if ($resource->region_id) {
                        $query->where(function ($query) use ($resource) {
                            $query->where(function ($query) {
                                $query->whereNull('region_id');
                            })->orWhere(function ($query) use ($resource) {
                                $query->where('region_id', $resource->region_id);
                            });
                        });
                    }
                } elseif ($resource instanceof \App\Models\User) {
                    $theCamp = $camp->vcamp;
                    $theApplicant = $resource->application_log->whereIn('batch_id', $theCamp?->batches()->pluck('id'))->first();
                    if ($theApplicant) {
                        $query->where(function ($query) use ($theApplicant) {
                            $query->where(function ($query) {
                                $query->whereNull('region_id');
                            })->orWhere(function ($query) use ($theApplicant) {
                                $query->where('region_id', $theApplicant->region_id);
                            });
                        });
                    }
                }
                return $query->where('camp_id', $camp->id);
            });
        };

        $this->rolePermissions = $this->with(['roles' => $constraint, 'roles.permissions'])->whereHas('roles', $constraint)->where('id', $this->id)->get()->pluck('roles')->flatten()->pluck('permissions')->flatten()->unique('id')->values();
        $permissions = $this->rolePermissions;
        $forInspect = $permissions->where("resource", "\\" . $class)->where("action", $action)->first();

        if ($forInspect) {
            if ($probing) {
                dump($forInspect);
            }
            switch ($forInspect->range_parsed) {
                // 0: na, all
                case 0:
                    // if (str_contains($class, "Applicant") && $context == "vcamp") {
                    //     // 志工名單中不能看到「只有報名但未編組」的人
                    //     return false;
                    // }
                    return true;
                    // 1: volunteer_large_group
                case 1:
                    if ($class == "App\Models\Volunteer" && $resource->user?->roles) {
                        return $resource->user->roles->whereIn("section", $this->roles()->where('camp_id', $camp->id)->pluck("section"))->count();
                    }
                    if ($class == "App\Models\Applicant" && $resource->user?->roles) {
                        return $resource->user->roles->whereIn("section", $this->roles()->where('camp_id', $camp->id)->pluck("section"))->count();
                    }
                    if (($class == "App\Models\User" || $class == "App\User") && $resource->roles) {
                        return $resource->roles->whereIn("section", $this->roles()->where('camp_id', $camp->id)->pluck("section"))->count();
                    }
                    if ($probing) {
                        dd("first if, case 1", $forInspect, $resource, $action, $camp, $context, $target, $permissions);
                    }
                    return false;
                    // 2: learner_group
                    // ★：學員小組的意思除了是「同一個小組的學員」以外，還包含「護持同一個學員小組的義工」
                case 2:
                    $roles = $this->roles()->where('group_id', '<>', null)->where("camp_id", $camp->id);
                    if (str_contains($class, "Applicant") && $context == "onlyCheckAvailability") {
                        return $roles->first();
                    }

                    if (str_contains($class, "Applicant") && !str_contains($class, "Group") && $target) {
                        return $roles->firstWhere('group_id', $target->group_id);
                    } elseif (str_contains($class, "Volunteer") && $target) {
                        return $roles->firstWhere(
                            'group_id',
                            $target->user?->roles()->where("position", "like", "%關懷小組%")->firstWhere('camp_id', $camp->id)?->group_id
                        )
                        ||
                        ($target->user?->roles()->where("position", "like", "%關懷小組%")->firstWhere('camp_id', $camp->id)?->group_id &&
                        $this->roles()->where("camp_id", $camp->id)->where(function ($query) {
                            $query->where("position", "like", "%關懷小組%")
                                ->orWhere("position", "like", "%關懷服務組%")
                                ->orWhere("position", "like", "%關服組%");
                        })->firstWhere('all_group', 1));
                    } elseif (str_contains($class, "User")) {
                        return $roles->firstWhere(
                            'group_id',
                            $target->roles()->where("position", "like", "%關懷小組%")->firstWhere('camp_id', $camp->id)?->group_id
                        )
                            ||
                            ($target->roles()->where("position", "like", "%關懷小組%")->firstWhere('camp_id', $camp->id)?->group_id &&
                                $this->roles()->where("camp_id", $camp->id)->where(function ($query) {
                                    $query->where("position", "like", "%關懷小組%")
                                        ->orWhere("position", "like", "%關懷服務組%")
                                        ->orWhere("position", "like", "%關服組%");
                                })->firstWhere('all_group', 1));
                    }

                    if ($class == "App\Models\ContactLog") {
                        if ($probing) {
                            dd("first if, case 2, ContactLog", $forInspect, $resource, $action, $camp, $context, $target, $permissions);
                        }
                        return $roles->firstWhere('group_id', $target->group_id);
                    }
                    if ($class == "App\Models\ApplicantsGroup") {
                        if ($probing) {
                            dd("first if, case 2, ApplicantsGroup", $forInspect, $resource, $action, $camp, $context, $target, $permissions);
                        }
                        return $roles->firstWhere('group_id', $target?->group_id ?? $resource->id);
                    }
                    if ($probing) {
                        dd("first if, case 2", $forInspect, $resource, $action, $camp, $context, $target, $permissions);
                    }
                    return false;
                    // 3: person
                case 3:
                    if (str_contains($class, "Applicant") && $context == "onlyCheckAvailability") {
                        return $this->caresLearners->whereIn('batch_id', $camp->batches->pluck('id'))->first();
                    }
                    if ($class == "App\Models\ApplicantGroup") {
                        return $this->caresLearners->where('group_id', '<>', null)->where("group_id", $resource->id)->first();
                    }
                    // 沒這回事
                    if ($class == "App\Models\CampOrg") {
                        return false;
                    }
                    if ($class == "App\Models\Applicant") {
                        return $this->caresLearners->where('group_id', '<>', null)->where("id", $resource->id)->first();
                    }
                    if ($class == "App\Models\ContactLog") {
                        return $this->caresLearners->where('group_id', '<>', null)->where("id", $target->id)->first();
                    }
                    if ($probing) {
                        dd("first if, case 3", $forInspect, $resource, $action, $camp, $context, $target, $permissions);
                    }
                    return false;
                default:
                    if ($probing) {
                        dd("first if, case default", $forInspect, $resource, $action, $camp, $context, $target, $permissions);
                    }
                    return false;
            }
        } elseif ($target && ((str_contains($class, "Applicant") || str_contains($class, "Volunteer")) && $action == "read")) {
            // $roles = $this->roles()->where('group_id', '<>', null)->where("camp_id", $camp->id);
            if ($probing) {
                dd("second if", $forInspect, $resource, $action, $camp, $context, $target, $permissions);
            }
            return false;
            // return $roles->firstWhere(
            //     'group_id',
            //     $target->user?->roles()->where("position", "like", "%關懷小組%")->firstWhere('camp_id', $camp->id)?->group_id
            // )
            // ||
            // ($target->user?->roles()->where("position", "like", "%關懷小組%")->firstWhere('camp_id', $camp->id)?->group_id &&
            // $this->roles()->where("camp_id", $camp->id)->where(function ($query) {
            //     $query->where("position", "like", "%關懷小組%")
            //         ->orWhere("position", "like", "%關懷服務組%")
            //         ->orWhere("position", "like", "%關服組%");
            // })->firstWhere('all_group', 1));
        } elseif ($target && (str_contains($class, "User") && ($context == "vcamp" || $context == "vcampExport") && $action == "read")) {
            $roles = $this->roles()->where("camp_id", $camp->id)->get();
            $theApplicant = $target->application_log->whereIn('batch_id', $camp->vcamp->batches()->pluck('id'))->first();
            $targetRoles = $target->roles()->where("camp_id", $camp->id)->get();
            if ($probing) {
                dd("third if", $forInspect, $resource, $action, $camp, $context, $target, $permissions);
            }
            $result = $roles->some(function ($role) use ($theApplicant, $targetRoles) {
                return $role->region_id == $theApplicant->region_id ||
                    ($role->group_id && $role->group_id == $targetRoles->firstWhere('group_id', $role->group_id)?->group_id) ||
                    ($role->region_id && $role->region_id == $targetRoles->firstWhere('region_id', $role->region_id)?->region_id);
            });
            return $result;
            // return $roles->firstWhere(
            //         'group_id',
            //         $target->roles()->where("position", "like", "%關懷小組%")->firstWhere('camp_id', $camp->id)?->group_id
            //     )
            //     ||
            //     ($target->roles()->where("position", "like", "%關懷小組%")->firstWhere('camp_id', $camp->id)?->group_id &&
            //         $this->roles()->where("camp_id", $camp->id)->where(function ($query) {
            //             $query->where("position", "like", "%關懷小組%")
            //                 ->orWhere("position", "like", "%關懷服務組%")
            //                 ->orWhere("position", "like", "%關服組%");
            //         })->firstWhere('all_group', 1));
        } else {
            if ($probing) {
                dd("else, all faild.", $forInspect, $resource, $action, $camp, $context, $target, $permissions);
            }
            return false;
        }
    }

    public function dynamic_stats(): MorphMany
    {
        return $this->morphMany(DynamicStat::class, 'urltable');
    }
}
