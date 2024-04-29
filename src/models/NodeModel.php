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

use Craft;
use craft\base\Model;
use studioespresso\navigate\Navigate;

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

    public $slug;

    public $order;

    public $parent;

    public $classes;

    public $enabled = true;

    public $elementId;

    public $elementType;

    public $children;

    public $blank = false;


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
            [['type', 'navId', 'siteId', 'name'], 'required'],
            [['id', 'name', 'navId', 'enabled', 'elementId', 'elementType', 'type', 'url', 'slug', 'siteId', 'order', 'parent', 'blank', 'classes', 'children'], 'safe'],
        ];
    }

    /**
     * Added to make migrating from amNav easier
     * @since 2.6.0
     * @return mixed
     * @throws \craft\errors\DeprecationException
     */
    public function listClass()
    {
        Craft::$app->getDeprecator()->log(__CLASS__ . '_listClass', "The 'listClass' method has been renamed to 'classes'.");
        return $this->classes;
    }


    /**
     * Added to make migrating from amNav easier
     * @since 2.6.0
     * @return mixed
     * @throws \craft\errors\DeprecationException
     */
    public function hasChildren()
    {
        Craft::$app->getDeprecator()->log(__CLASS__ . '_hasChildren', "The 'hasChildren' method has been renamed to 'children'.");
        return $this->children;
    }

    public function getChildren($includeDisabled = false)
    {
        return Navigate::$plugin->nodes->getChildrenByNode($this, $includeDisabled);
    }

    public function active()
    {
        switch ($this->type) {
            case 'url':
                if (substr(Craft::$app->request->getPathInfo(), 0, strlen($this->url)) === $this->url) {
                    return true;
                }
                if (substr("/" . Craft::$app->request->getPathInfo(), 0, strlen($this->url)) === $this->url) {
                    return true;
                }

                break;
            default:
                if ($this->url === Craft::$app->request->getAbsoluteUrl()) {
                    return true;
                }
                
                if (strpos(Craft::$app->request->getAbsoluteUrl(), '?')) {
                    if (explode('?', Craft::$app->request->getAbsoluteUrl())[0] === $this->url) {
                        return true;
                    }
                }
                if (substr(Craft::$app->request->getPathInfo(), 0, strlen($this->slug . "/")) === $this->slug . "/") {
                    return true;
                }
                break;
        }
        return false;
    }

    public function current()
    {
        switch ($this->type) {
            case 'url':
                if (substr(Craft::$app->request->getPathInfo(), 0, strlen($this->url)) === $this->url) {
                    return true;
                }
                if (substr("/" . Craft::$app->request->getPathInfo(), 0, strlen($this->url)) === $this->url) {
                    return true;
                }

                break;
            default:
                if ($this->url === Craft::$app->request->getAbsoluteUrl()) {
                    return true;
                }
                
                if (strpos(Craft::$app->request->getAbsoluteUrl(), '?')) {
                    if (explode('?', Craft::$app->request->getAbsoluteUrl())[0] === $this->url) {
                        return true;
                    }
                }
                break;
        }
        return false;
    }
}
