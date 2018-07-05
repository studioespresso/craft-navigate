<?php

namespace studioespresso\navigate\migrations;

use Craft;
use craft\db\Migration;
use studioespresso\navigate\records\NodeRecord;
use Twig\Node\Node;

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
        $this->update(NodeRecord::tableName(), [
            // $name, $table, $columns, $refTable, $refColumns, $delete = null, $update = null)
            'parent' => $this->addForeignKey(
                $this->db->getForeignKeyName(NodeRecord::tableName(), 'parent'),
                NodeRecord::tableName(),
                'parent',
                NodeRecord::tableName(),
                'id',
                'CASCADE'
            )]);
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
