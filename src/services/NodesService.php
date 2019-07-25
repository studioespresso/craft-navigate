<?php
/**
 * Navigate plugin for Craft CMS 3.x
 *
 * Navigation plugin for Craft 3
 *
 * @link      https://studioespresso.co
 * @copyright Copyright (c) 2018 Studio Espresso
 */

namespace studioespresso\navigate\services;

use Craft;
use craft\base\Component;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\db\ElementQuery;
use craft\elements\Entry;
use putyourlightson\blitz\Blitz;
use studioespresso\navigate\models\NavigationModel;
use studioespresso\navigate\models\NodeModel;
use studioespresso\navigate\Navigate;
use studioespresso\navigate\records\NodeRecord;
use yii\caching\TagDependency;
use yii\web\NotFoundHttpException;

/**
 * NodesService Service
 *
 * All of your plugin’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    Studio Espresso
 * @package   Navigate
 * @since     0.0.1
 */
class NodesService extends Component
{

    const NAVIGATE_CACHE = "navigate_cache";
    const NAVIGATE_CACHE_NODES = "navigate_cache_nodes";

    public $types = [
        'entry' => 'Entry',
        'url' => 'Url',
        'asset' => 'Asset',
        'category' => 'Category',
        'heading' => 'Heading',
    ];

    private $_nodes = [];

    private $_elements = [];

    public function getNodesByNavId($navId = null)
    {
        $query = NodeRecord::find();
        $query->where(['navId' => $navId]);
        $query->orderBy('order');
        return $query->all();
    }


    public function getNodesForRender($navHandle, $site)
    {
        Craft::beginProfile('getNodesForRender', __METHOD__);
        $nav = Navigate::$plugin->navigate->getNavigationByHandle($navHandle);
        if (!$nav) {
            return false;
        }

        $cacheTags = new TagDependency([
            'tags' => [
                self::NAVIGATE_CACHE,

                self::NAVIGATE_CACHE_NODES,
                self::NAVIGATE_CACHE_NODES . '_' . $nav->handle . '_' . $site
            ]
        ]);

        $nodes = Craft::$app->getCache()->getOrSet(
            self::NAVIGATE_CACHE_NODES . '_' . $nav->handle . '_' . $site,
            function () use ($nav, $site) {
                $nodes = $this->getNodesByNavIdAndSiteById($nav->id, $site, true, true);
                $nodes = $this->parseNodesForRender($nodes, $nav);
                return $nodes;
            },
            null,
            $cacheTags
        );

        Craft::endProfile('getNodesForRender', __METHOD__);
        return $nodes;
    }

    private function parseNodesForRender(array $nodes, $nav)
    {
        $data = [];
        foreach ($nodes as $node) {
            /* @var $node NodeModel */
            $node = $this->parseNode($node, $nav);
            if ($node) {
                $data[$node->order] = $node;
            }
        }
        return $data;
    }

    private function parseNode(NodeModel $node, $nav)
    {
        if (isset($this->_nodes[$node->id])) {
            return $this->_nodes[$node->id];
        }
        if ($node->type === 'element') {
            if (isset($this->_elements[$node->siteId][$node->elementId])) {
                $element = $this->_elements[$node->siteId][$node->elementId];
            } else {
                if ($node->elementType == 'entry') {
                    $query = Entry::find();
                } elseif ($node->elementType === 'asset') {
                    $query = Asset::find();
                } elseif ($node->elementType === 'category') {
                    $query = Category::find();
                }
                $query->siteId($node->siteId);
                $query->id($node->elementId);
                $element = $query->one();
            }

            if ($element && $element->enabled) {
                $node->url = $element->getUrl();
                $node->slug = $element->uri;
                $this->_elements[$node->siteId][$node->elementId] = $element;
            } else {
                return false;
            }

        } elseif ($node->type === 'url') {
            $url = Craft::parseEnv($node->url);
            $node->url = Craft::$app->view->renderObjectTemplate($url, Craft::$app->getConfig()->general);
        }
        if ($nav->levels > 1) {
            $node->children = $node->getChildren();
            if ($node->children) {
                foreach ($node->children as $child) {
                    $node->children[$child->order] = $this->parseNode($child, $nav);
                }
            }
        }
        $this->_nodes[$node->id] = $node;
        return $node;
    }

    public function getChildrenByNode(NodeModel $node, $includeDisabled = true)
    {
        $data = [];
        $query = NodeRecord::find();
        $query->where(['navId' => $node->navId, 'siteId' => $node->siteId, 'parent' => $node->id]);
        if (!$includeDisabled) {
            $query->andWhere(['enabled' => 1]);
        }
        $query->orderBy('order');
        foreach ($query->all() as $record) {
            $model = new NodeModel();
            $model->setAttributes($record->getAttributes());
            $data[$model->order] = $model;
        }
        return $data;
    }

    public function getNodesByNavIdAndSiteById($navId = null, $siteId, $refresh = false, $excludeDisabled = false)
    {
        $query = NodeRecord::find();
        $query->where(['navId' => $navId, 'siteId' => $siteId, 'parent' => null]);
        if ($excludeDisabled) {
            $query->andWhere(['enabled' => 1]);
        }
        $query->orderBy('parent ASC, order ASC');
        $data = [];
        foreach ($query->all() as $record) {
            $model = new NodeModel();
            $model->setAttributes($record->getAttributes());
            $data[$model->id] = $model;
        }
        return $data;
    }

    public function getNodesStructureByNavIdAndSiteById($navId = null, $siteId)
    {
        $query = NodeRecord::find();
        $query->where(['navId' => $navId, 'siteId' => $siteId]);
        $query->orderBy('parent ASC, order ASC');
        $data = [];
        foreach ($query->all() as $record) {
            $model = new NodeModel();
            $model->setAttributes($record->getAttributes());
            $data[$model->id] = $model;
        }
        return $data;
    }

    public function getNodeById($id = null)
    {
        $query = NodeRecord::findOne([
            'id' => $id
        ]);
        if ($query) {
            $model = new NodeModel();
            $model->setAttributes($query->getAttributes());
            return $model;
        }
        return false;
    }

    public function getNodeUrl(NodeModel $node)
    {
        if ($node->type === "url") {
            return $node->url;
        } else {
            $element = Craft::$app->elements->getElementById($node->elementId);
            return $element->getUrl();
        }
    }

    public function getNodeTypes(NavigationModel $navigation)
    {
        $nodeTypes = [];
        if ($navigation->allowedSources === "*") {
            foreach ($this->types as $handle => $title) {
                $nodeTypes[] = [
                    'handle' => $handle,
                    'title' => $title,
                ];
            }
        } else {
            foreach (json_decode($navigation->allowedSources) as $type) {
                $nodeTypes[] = [
                    'handle' => $type,
                    'title' => $this->types[$type],
                ];
            }
        }

        return $nodeTypes;
    }

    public function deleteNode(NodeModel $node)
    {
        if (isset($node->id)) {
            if (NodeRecord::deleteAll([
                'id' => $node->id
            ])) {
                NodeRecord::deleteAll([
                    'parent' => $node->id
                ]);
            };
        } else {
            throw new NotFoundHttpException('Node not found', 404);
        }

        $this->_clearCacheForNav($node);
        return true;
    }

    public function save(NodeModel $node)
    {

        $isNew = !$node->id;

        if (isset($node->id)) {
            $record = NodeRecord::findOne([
                'id' => $node->id
            ]);
        } else {
            $record = new NodeRecord();
        }

        if ($isNew) {
            $node->order = $this->getOrderForNewNode($node->navId, $node->siteId, $node->parent ? $node->parent : null);
        }

        $record->siteId = $node->siteId;
        $record->navId = $node->navId;
        $record->name = $node->name;
        $record->type = $node->type;
        $record->order = $node->order;
        $record->parent = $node->parent;
        $record->enabled = $node->enabled ? 1 : 0;
        $record->blank = $node->blank ? 1 : 0;
        $record->classes = $node->classes;
        $record->elementType = $node->elementType;
        $record->elementId = $node->elementId;
        $record->url = $node->url;

        $save = $record->save();

        if (!$save) {
            Craft::getLogger()->log($record->getErrors(), LOG_ERR, 'navigate');
            return false;
        }

        $node = $this->getNodeById($record->id);
        $this->_clearCacheForNav($node);
        return $node;
    }

    public function move(NodeModel $node, $parent, $previousId)
    {
        /** @var NodeRecord $object */
        $record = NodeRecord::findOne(['id' => $node->id]);
        if (!$record) {
            return false;
        }

        $record->parent = $parent;
        $currentOrder = 0;

        if ($previousId === false) {
            $record->order = $currentOrder;
            $currentOrder++;
        }

        $nodes = $this->getNodesStructureByNavIdAndSiteById($record->navId, $record->siteId);
        foreach ($nodes as $node) {
            if ($parent == $node->parent) {
                if ($previousId && $previousId == $node->id) {
                    $this->updateNode($node, $currentOrder);
                    $currentOrder++;
                    $record->order = $currentOrder;
                } else {
                    $this->updateNode($node, $currentOrder);
                }
            }
            $currentOrder++;
        }

        $record->save();
        $this->_clearCacheForNav($node);
        return true;
    }

    public function deleteNodesByNavId($record)
    {
        $records = NodeRecord::findAll([
            'navId' => $record->id,
        ]);

        foreach ($records as $record) {
            $record->delete();
        }
        return;
    }

    private function getOrderForNewNode($nav, $site, $parent)
    {
        $query = NodeRecord::find();
        $query->where(
            [
                'navId' => $nav,
                'siteId' => $site,
                'parent' => $parent
            ]);
        $query->orderBy('order DESC');
        $query->limit(1);
        $result = $query->one();

        if ($result) {
            return (int)$result->order + 1;
        }
        return 0;

    }

    private function updateNode(NodeModel $node, $order)
    {
        $record = NodeRecord::findOne(['id' => $node->id]);
        $record->setAttribute('order', $order);
        $result = $record->save();
        $this->_clearCacheForNav($node);
        return $result;
    }

    private function _clearCacheForNav(NodeModel $node)
    {
        $nav = Navigate::getInstance()->navigate->getNavigationById($node->navId);
        TagDependency::invalidate(
            Craft::$app->getCache(),
            [self::NAVIGATE_CACHE_NODES . '_' . $nav->handle . '_' . $node->siteId]
        );

        // If putyourlightson/craft-blitz is installed & activacted, clear that cache too
        if (Craft::$app->getPlugins()->isPluginEnabled('blitz')) {
            if (version_compare(Blitz::$plugin->getVersion(), "2.0.1") >= 0) {
                Blitz::$plugin->flushCache->flushAll();
                Blitz::$plugin->clearCache->clearAll();
            }
        }
        return;
    }

}
