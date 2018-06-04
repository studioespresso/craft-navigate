<?php
/**
 * Navigate plugin for Craft CMS 3.x
 *
 * Navigation plugin for Craft 3
 *
 * @link      https://studioespresso.co
 * @copyright Copyright (c) 2018 Studio Espresso
 */

namespace studioespresso\navigate\models;

use craft\validators\HandleValidator;
use studioespresso\navigate\Navigate;

use Craft;
use craft\base\Model;
use yii\bootstrap\Nav;

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
class NodeModel extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * Some field model attribute
     *
     * @var string
     */
    public $id;

    public $name;

    public $navId;

    public $siteId;

    public $type;

    public $url;

    public $order;

    public $enabled = true;

    public $elementId;

    public $elementType;

    public $blank = false;

    public $parentId;

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
    public function rules()
    {
        return [
            [['type', 'navId', 'siteId', 'name'], 'required'],
            [[ 'id', 'name', 'navId', 'enabled', 'elementId', 'elementType', 'type', 'url', 'siteId', 'order'], 'safe'],
        ];
    }

}
