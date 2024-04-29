<?php
/**
 * Navigate plugin for Craft CMS 3.x
 *
 * Navigation plugin for Craft 3
 *
 * @link      https://studioespresso.dev
 * @copyright Copyright (c) 2018 Studio Espresso
 */

namespace studioespresso\navigate\models;

use craft\base\Model;

/**
 * Navigate Settings Model
 *
 * This is a model used to define the plugin's settings.
 *
 * Models are containers for data. Just about every time information is passed
 * between services, controllers, and templates in Craft, it’s passed via a model.
 *
 * https://craftcms.com/docs/plugins/models
 *
 * @author    Studio Espresso
 * @package   Navigate
 * @since     0.0.1
 */
class Settings extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * Some field model attribute
     *
     * @var string
     */
    public $pluginLabel = 'Navigate';

    public $anyoneCanAdd = false;

    public $disableCaching = false;

    public $allowWhenReadOnly = false;

    public $nodeClasses = [];

    // Public Methods
    // =========================================================================

    /**
     * Returns the validation rules for attributes.
     *
     * Validation rules are used by [[validate()]] to check if attribute values are valid.
     * Child classes may override this method to declare different validation rules.
     *
     * More info: http://www.yiiframework.com/doc-2.0/guide-input-validation.html
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            ['pluginLabel', 'string'],
            ['anyoneCanAdd', 'boolean'],
            ['allowWhenReadOnly', 'boolean'],
            ['disableCaching', 'boolean'],
            ['nodeClasses', 'checkIsArray'],
            ['pluginLabel', 'default', 'value' => 'Navigate'],
        ];
    }

    public function checkIsArray()
    {
        if (!is_array($this->nodeClasses)) {
            $this->addError('nodeClasses','nodeClasses is not array!');
        }
    }
}
