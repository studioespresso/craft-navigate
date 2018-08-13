<?php

namespace studioespresso\navigate\migrations;

use Craft;
use craft\db\Migration;
use studioespresso\navigate\records\NavigationRecord;
use studioespresso\navigate\records\NodeRecord;

/**
 * m180813_182019_addIndexes migration.
 */
class m180813_182019_addIndexes extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // seomatic_metabundles table
        $this->createIndex(
            $this->db->getIndexName(
                NavigationRecord::tableName(),
                'handle',
                false
            ),
            NavigationRecord::tableName(),
            'handle',
            false
        );

        $this->createIndex(
            $this->db->getIndexName(
                NodeRecord::tableName(),
                'id',
                false
            ),
            NodeRecord::tableName(),
            'id',
            false
        );
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180813_182019_addIndexes cannot be reverted.\n";
        return false;
    }
}
