<?php

namespace studioespresso\navigate\migrations;

use craft\db\Migration;
use studioespresso\navigate\records\NavigationRecord;

/**
 * m190829_193907_addEnabledSiteGroupsOption migration.
 */
class m190829_193907_addEnabledSiteGroupsOption extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(NavigationRecord::tableName(), 'enabledSiteGroups', $this->text()->after('allowedSources'));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190829_193907_addEnabledSiteGroupsOption cannot be reverted.\n";
        return false;
    }
}
