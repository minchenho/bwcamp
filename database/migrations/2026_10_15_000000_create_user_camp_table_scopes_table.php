<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * event_admin 可管理的營隊類型（對應 camps.table，只記主營隊類型，義工營自動包含）
     */
    public function up()
    {
        Schema::create('user_camp_table_scopes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('camp_table');
            $table->timestamps();

            $table->unique(['user_id', 'camp_table']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_camp_table_scopes');
    }
};
