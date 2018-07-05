<?php
/**
 * Navigate plugin for Craft CMS 3.x
 *
 * Navigation plugin for Craft 3
 *
 * @link      https://studioespresso.co
 * @copyright Copyright (c) 2018 Studio Espresso
 */

namespace studioespresso\navigate\migrations;

use studioespresso\navigate\Navigate;

use Craft;
use craft\config\DbConfig;
use craft\db\Migration;
use studioespresso\navigate\records\NavigationRecord;
use studioespresso\navigate\records\NodeRecord;
use Twig\Node\Node;

/**
 * Navigate Install Migration
 *
 * If your plugin needs to create any custom database tables when it gets installed,
 * create a migrations/ folder within your plugin folder, and save an Install.php file
 * within it using the following template:
 *
 * If you need to perform any additional actions on install/uninstall, override the
 * safeUp() and safeDown() methods.
 *
 * @author    Studio Espresso
 * @package   Navigate
 * @since     0.0.1
 */
class Install extends Migration
{
    // Public Properties
    // =========================================================================

    /**
     * @var string The database driver to use
     */
    public $driver;

    // Public Methods
    // =========================================================================

    /**
     * This method contains the logic to be executed when applying this migration.
     * This method differs from [[up()]] in that the DB logic implemented here will
     * be enclosed within a DB transaction.
     * Child classes may implement this method instead of [[up()]] if the DB logic
     * needs to be within a transaction.
     *
     * @return boolean return a false value to indicate the migration fails
     * and should not proceed further. All other return values mean the migration succeeds.
     */
    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            $this->addForeignKeys();
            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
            $this->insertDefaultData();
        }

        return true;
    }

    /**
     * This method contains the logic to be executed when removing this migration.
     * This method differs from [[down()]] in that the DB logic implemented here will
     * be enclosed within a DB transaction.
     * Child classes may implement this method instead of [[down()]] if the DB logic
     * needs to be within a transaction.
     *
     * @return boolean return a false value to indicate the migration fails
     * and should not proceed further. All other return values mean the migration succeeds.
     */
    public function safeDown()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();

        return true;
    }

    // Protected Methods
    // =========================================================================

    /**
     * Creates the tables needed for the Records used by the plugin
     *
     * @return bool
     */


    protected function createTables()
    {
        $tablesCreated = false;

    // navigate_navigaterecord table
        $tableSchema = Craft::$app->db->schema->getTableSchema(NavigationRecord::tableName());
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                NavigationRecord::tableName(),
                [
                    'id' => $this->primaryKey(),
                    'title' => $this->string(255)->notNull()->defaultValue(''),
                    'allowedSources' => $this->text(),
                    'defaultNodeType' => $this->text(20),
                    'levels' => $this->integer(1)->defaultValue(1),
                    'adminOnly' => $this->boolean()->defaultValue(false),
                    'handle' => $this->string(255)->notNull()->defaultValue(''),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
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
                    'enabled' => $this->boolean()->defaultValue(1),
                    'blank' => $this->boolean()->defaultValue(0),
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
            $this->db->getForeignKeyName('{{%navigate_nodes}}', 'siteId'),
            '{{%navigate_nodes}}',
            'siteId',
            '{{%sites}}',
            'id',
            'CASCADE'
        );

    }

    /**
     * Populates the DB with the default data.
     *
     * @return void
     */
    protected function insertDefaultData()
    {
    }

    /**
     * Removes the tables needed for the Records used by the plugin
     *
     * @return void
     */
    protected function removeTables()
    {
    // navigate_navigaterecord table
        $this->dropTableIfExists(NavigationRecord::tableName());
        $this->dropTableIfExists(NodeRecord::tableName());
    }
}
