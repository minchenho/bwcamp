<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 權限範本：只記錄權限「內容」(resource / action / range)，
     * 套用到職務時才依職務的 camp_id / batch_id 產生實際權限
     */
    public function up()
    {
        Schema::create('permission_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            // 限定營隊類型（對應 camps.table），null 表示通用
            $table->string('camp_table')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('permission_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('permission_templates')->cascadeOnDelete();
            $table->string('resource');
            $table->string('action');
            $table->string('range');
            $table->timestamps();

            $table->unique(['template_id', 'resource', 'action']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('permission_template_items');
        Schema::dropIfExists('permission_templates');
    }
};
