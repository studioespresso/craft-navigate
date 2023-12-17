<?php

namespace studioespresso\navigate\migrations;

use craft\db\Migration;
use studioespresso\navigate\records\NodeRecord;

/**
 * m180705_164128_relateNodes migration.
 */
class m180705_164128_relateNodes extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addForeignKey(
            $this->db->getForeignKeyName(NodeRecord::tableName(), 'parent'),
            NodeRecord::tableName(),
            'parent',
            NodeRecord::tableName(),
            'id',
            'CASCADE'
        );
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180705_164128_relateNodes cannot be reverted.\n";
        return false;
    }
}
