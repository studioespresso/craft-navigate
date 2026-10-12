<?php

use CraftCms\Cms\Database\Migration;
use CraftCms\Cms\Database\Table;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('navigate_navigations')) {
            Schema::create('navigate_navigations', function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('title')->default('');
                $table->string('handle')->default('')->index();
                $table->text('allowedSources')->nullable();
                $table->text('enabledSiteGroups')->nullable();
                $table->integer('levels')->default(1)->nullable();
                $table->boolean('adminOnly')->default(false)->nullable();
                $table->dateTime('dateCreated');
                $table->dateTime('dateUpdated');
                $table->dateTime('dateDeleted')->nullable();
                $table->char('uid', 36)->default('0');
            });
        }

        if (! Schema::hasTable('navigate_nodes')) {
            Schema::create('navigate_nodes', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('siteId');
                $table->integer('navId');
                $table->string('name');
                $table->string('url')->nullable();
                $table->integer('elementId')->nullable();
                $table->string('elementType', 20)->nullable();
                $table->string('type', 20)->nullable();
                $table->boolean('enabled')->default(true)->nullable();
                $table->boolean('blank')->default(false)->nullable();
                $table->string('classes')->nullable();
                $table->integer('parent')->nullable();
                $table->integer('order')->nullable();
                $table->dateTime('dateCreated');
                $table->dateTime('dateUpdated');
                $table->char('uid', 36)->default('0');
                $table->index(['navId', 'siteId', 'parent']);
                $table->foreign('siteId')->references('id')->on(Table::SITES)->cascadeOnDelete();
                // Deleting a node deletes its descendants
                $table->foreign('parent')->references('id')->on('navigate_nodes')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('navigate_nodes');
        Schema::dropIfExists('navigate_navigations');
    }
};
