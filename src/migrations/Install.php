<?php
/**
 * Navigate plugin for Craft CMS
 *
 * Navigation plugin for Craft CMS
 *
 * @link      https://studioespresso.co
 * @copyright Copyright (c) 2018 Studio Espresso
 */

namespace studioespresso\navigate\migrations;

use Craft;
use craft\db\Migration;
use studioespresso\navigate\records\NavigationRecord;
use studioespresso\navigate\records\NodeRecord;

class Install extends Migration
{
    // Public Methods
    // =========================================================================
    public function safeUp()
    {
        if ($this->createTables()) {
            $this->addForeignKeys();
            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
        }

        return true;
    }

    public function safeDown()
    {
        $this->dropTableIfExists(NavigationRecord::tableName());
        $this->dropTableIfExists(NodeRecord::tableName());

        return true;
    }

    // Protected Methods
    // =========================================================================
    protected function createTables()
    {
        $tablesCreated = false;

        $tableSchema = Craft::$app->db->schema->getTableSchema(NavigationRecord::tableName());
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                NavigationRecord::tableName(),
                [
                    'id' => $this->primaryKey(),
                    'title' => $this->string(255)->notNull()->defaultValue(''),
                    'allowedSources' => $this->text(),
                    'enabledSiteGroups' => $this->text(),
                    'levels' => $this->integer(1)->defaultValue(1),
                    'adminOnly' => $this->boolean()->defaultValue(false),
                    'handle' => $this->string(255)->notNull()->defaultValue(''),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'dateDeleted' => $this->dateTime()->null(),
                    'uid' => $this->uid(),

                ]
            );

            $this->createTable(
                NodeRecord::tableName(),
                [
                    'id' => $this->primaryKey(),
                    'siteId' => $this->integer(11)->notNull(),
                    'navId' => $this->integer(11)->notNull()->notNull(),
                    'name' => $this->string(255)->notNull(),
                    'url' => $this->string(255),
                    'elementId' => $this->integer(10),
                    'elementType' => $this->string(20),
                    'type' => $this->string(20),
                    'enabled' => $this->boolean()->defaultValue(true),
                    'blank' => $this->boolean()->defaultValue(false),
                    'classes' => $this->string(255),
                    'parent' => $this->integer(10)->defaultValue(0),
                    'order' => $this->integer(10),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),

                ]
            );
        }

        return $tablesCreated;
    }


    /**
     * Creates the foreign keys needed for the Records used by the plugin
     *
     * @return void
     */
    protected function addForeignKeys()
    {

        // $name, $table, $columns, $refTable, $refColumns, $delete = null, $update = null)
        $this->addForeignKey(
            $this->db->getForeignKeyName(),
            '{{%navigate_nodes}}',
            'siteId',
            '{{%sites}}',
            'id',
            'CASCADE'
        );
    }
}
