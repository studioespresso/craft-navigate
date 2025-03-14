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
 * @author    Studio Espresso
 * @package   Navigate
 * @since     0.0.1
 *
 * @property String|array $enabledSiteGroups
 * @property String $handle
 * @property String $title
 * @property String $allowedSources
 * @property int $levels
 * @property bool $adminOnly
 */
class NavigationRecord extends ActiveRecord
{
    // Public Static Methods
    // =========================================================================


    public static function tableName()
    {
        return '{{%navigate_navigations}}';
    }
}
