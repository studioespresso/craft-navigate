<?php
/**
 * Navigate plugin for Craft CMS
 *
 * @link      https://studioespresso.co
 * @copyright Copyright (c) 2018 Studio Espresso
 */

namespace studioespresso\navigate\records;

use craft\db\ActiveRecord;

/**
 *
 * @author    Studio Espresso
 * @package   Navigate
 * @since     0.0.1
 *
 * @property int $id
 * @property int $navId
 * @property int $siteId
 * @property int $parent
 * @property int $order
 * @property string $type
 * @property string $elementId
 * @property string $elementType
 * @property string $url
 * @property string $classes
 * @property bool $blank
 * @property bool $enabled
 * @property string $name
 */
class NodeRecord extends ActiveRecord
{
    // Public Static Methods
    // =========================================================================
    public static function tableName()
    {
        return '{{%navigate_nodes}}';
    }
}
