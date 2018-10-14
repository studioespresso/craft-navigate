<?php

namespace studioespresso\navigate\migrations;

use Craft;
use craft\db\Migration;
use studioespresso\navigate\records\NavigationRecord;

/**
 * m181014_100025_softDeletes migration.
 */
class m181014_100025_softDeletes extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(NavigationRecord::tableName(), 'dateDeleted', $this->dateTime()->null()->after('dateUpdated'));
        $this->createIndex(null, NavigationRecord::tableName(), NavigationRecord::tableName(), false);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m181014_100025_softDeletes cannot be reverted.\n";
        return false;
    }
}
