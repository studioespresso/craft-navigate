<?php
/**
 * Navigate plugin for Craft CMS
 *
 * Navigation plugin for Craft CMS
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
     * @return mixed
     * @throws \craft\errors\DeprecationException
     * @since 2.6.0
     */
    public function listClass()
    {
        Craft::$app->getDeprecator()->log(__CLASS__ . '_listClass', "The 'listClass' method has been renamed to 'classes'.");
        return $this->classes;
    }


    /**
     * Added to make migrating from amNav easier
     * @return mixed
     * @throws \craft\errors\DeprecationException
     * @since 2.6.0
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
        return $this->matchesRequest(true);
    }

    public function current()
    {
        return $this->matchesRequest(false);
    }

    /**
     * Compares the node's URL with the current request: same host, and the same path
     * (or, with $includeDescendants, a path below it). The homepage only matches itself.
     */
    private function matchesRequest(bool $includeDescendants): bool
    {
        if ($this->type === 'heading' || !$this->url) {
            return false;
        }

        $node = parse_url((string)$this->url);
        if ($node === false || (isset($node['scheme']) && !in_array(strtolower($node['scheme']), ['http', 'https']))) {
            return false;
        }

        $request = Craft::$app->getRequest();
        if (isset($node['host']) && strcasecmp($node['host'], (string)$request->getHostName()) !== 0) {
            return false;
        }

        // Fragment- or query-only links ("#contact", "?page=2") don't point to a page
        if (!isset($node['host']) && ($node['path'] ?? '') === '') {
            return false;
        }

        $nodePath = trim($node['path'] ?? '', '/');
        $currentPath = trim((string)parse_url($request->getAbsoluteUrl(), PHP_URL_PATH), '/');

        if ($nodePath === $currentPath) {
            return true;
        }

        return $includeDescendants && $nodePath !== '' && str_starts_with($currentPath . '/', $nodePath . '/');
    }
}
