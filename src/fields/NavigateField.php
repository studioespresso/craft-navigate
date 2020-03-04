<?php
/**
 * Date Range plugin for Craft CMS 3.x
 *
 * Date range field
 *
 * @link      https://studioespresso.co/en
 * @copyright Copyright (c) 2019 Studio Espresso
 */

namespace studioespresso\navigate\fields;

use Craft;
use craft\fields\Dropdown;
use studioespresso\navigate\Navigate;

/**
 * @author    Studio Espresso
 * @package   DateRange
 * @since     1.0.0
 */
class NavigateField extends Dropdown
{
    // Public Properties
    // =========================================================================

    public function init()
    {
        parent::init();

        $options = [];
        $options[] = ['label' => Craft::t('navigate', 'Select a navigation'), 'value' => '-'];
        $navigations = Navigate::getInstance()->navigate->getAllNavigations();
        foreach ($navigations as $nav) {
            $options[] = [
                'label' => $nav->title,
                'value' => $nav->handle
            ];
        }
        $this->options = $options;

    }

    /**
     *
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('navigate', 'Navigation');
    }

    // Public Methods
    // =========================================================================
    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        return false;
    }
}
