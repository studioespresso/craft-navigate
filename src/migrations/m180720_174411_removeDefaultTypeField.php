<?php

namespace studioespresso\navigate\migrations;

use Craft;
use craft\db\Migration;
use studioespresso\navigate\records\NavigationRecord;

/**
 * m180720_174411_removeDefaultTypeField migration.
 */
class m180720_174411_removeDefaultTypeField extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->dropColumn(NavigationRecord::tableName(), 'defaultNodeType');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180720_174411_removeDefaultTypeField cannot be reverted.\n";
        return false;
    }
}
