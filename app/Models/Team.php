<?php

namespace App\Models;

use Laratrust\Models\Team as LaratrustTeam;

class Team extends LaratrustTeam
{
    public $guarded = [];

    public $resourceNameInMandarin = '職務權限組別（未使用）';
}
