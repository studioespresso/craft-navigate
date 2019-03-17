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
        'category' => 'Category'
    ];

    private $_nodes = [];

    private $_elements = [];

    private $_navs = [];

    private $_nav_nodes = [];

    public function getNodesByNavId($navId = null)
    {
        $query = NodeRecord::find();
        $query->where(['navId' => $navId]);
        $query->orderBy('order');
        return $query->all();
    }


    public function getNodesForRender($navHandle, $site)
    {
        if (isset($this->_navs[$navHandle])) {
            $nav = $this->_navs[$navHandle];
        } else {
            $nav = Navigate::$plugin->navigate->getNavigationByHandle($navHandle);
            $this->_navs[$navHandle] = $nav;
        }

        if (!$nav) {
            return false;
        }

        $cacheTags = new TagDependency([
            'tags' => [
                self::NAVIGATE_CACHE,
                self::NAVIGATE_CACHE_NODES,
                self::NAVIGATE_CACHE_NODES . '_' . $navHandle . '_' . $site
            ]
        ]);

        Craft::beginProfile('getNodesForNav', __METHOD__);

        $nodes = Craft::$app->getCache()->getOrSet(
            self::NAVIGATE_CACHE_NODES . '_' . $navHandle . '_' . $site,
            function () use ($nav, $site) {
                $nodes = $this->getNodesByNavIdAndSiteById($nav->id, $site, true, true);
                $nodes = $this->parseNodesForRender($nodes, $nav);
                return $nodes;
            },
            null,
            $cacheTags
        );

        Craft::endProfile('getNodesForNav', __METHOD__);

        return $nodes;
    }

    public function setNodeCache($navId, $siteId)
    {
        $nav = Navigate::$plugin->navigate->getNavigationById($navId);
        if (!$nav) {
            return false;
        }


        try {
            $nodes = $this->getNodesByNavIdAndSiteById($nav->id, $siteId, true, true);
            $nodes = $this->parseNodesForRender($nodes, $nav);

            $cacheTags = new TagDependency([
                'tags' => [
                    self::NAVIGATE_CACHE,
                    self::NAVIGATE_CACHE_NODES,
                    self::NAVIGATE_CACHE_NODES . '_' . $nav->handle . '_' . $siteId
                ]
            ]);

            Craft::$app->cache->set(
                self::NAVIGATE_CACHE_NODES . '_' . $nav->handle . '_' . $siteId,
                $nodes,
                null,
                $cacheTags
            );

            // If putyourlightson/craft-blitz is installed & activacted, clear that cache too
            if (Craft::$app->getPlugins()->isPluginEnabled('blitz')) {
                if (version_compare(Blitz::$plugin->getVersion(), "2.0.1") >= 0) {
                    Blitz::$plugin->flushCache->flushAll();
                }
            }

            return true;
        } catch (\Exception $e) {
            Navigate::error('Error building navigation cache');
        }

        return;
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
                    $query = new ElementQuery(Entry::class);
                } elseif ($node->elementType === 'asset') {
                    $query = new ElementQuery(Asset::class);
                } elseif ($node->elementType === 'category') {
                    $query = new ElementQuery(Category::class);
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

    public function getChildrenByNode(NodeModel $node)
    {
        $data = [];
        $query = NodeRecord::find();
        $query->where(['navId' => $node->navId, 'siteId' => $node->siteId, 'parent' => $node->id, 'enabled' => 1]);
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

    public function getNodesStructureByNavIdAndSiteById($navId = null, $siteId, $refresh = false)
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

    public function getNodeById(int $navId = null)
    {
        $query = NodeRecord::findOne([
            'id' => $navId
        ]);
        if ($query) {
            $model = new NodeModel();
            $model->setAttributes($query->getAttributes());
            return $model;
        }
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

    public function deleteNode(NodeModel $model)
    {
        if (isset($model->id)) {
            $this->setNodeCache($model->navId, $model->siteId);
            if (NodeRecord::deleteAll([
                'id' => $model->id
            ])) {
                NodeRecord::deleteAll([
                    'parent' => $model->id
                ]);
            };
        } else {
            throw new NotFoundHttpException('Node not found', 404);
        }

        $this->setNodeCache($model->navId, $model->siteId);
        return true;
    }

    public function save(NodeModel $model)
    {

        $isNew = !$model->id;

        $record = false;
        if (isset($model->id)) {
            $record = NodeRecord::findOne([
                'id' => $model->id
            ]);
        } else {
            $record = new NodeRecord();
        }

        if ($isNew) {
            $model->order = $this->getOrderForNewNode($model->navId, $model->siteId, $model->parent ? $model->parent : null);
        }

        $record->siteId = $model->siteId;
        $record->navId = $model->navId;
        $record->name = $model->name;
        $record->type = $model->type;
        $record->order = $model->order;
        $record->parent = $model->parent;
        $record->enabled = $model->enabled ? 1 : 0;
        $record->blank = $model->blank ? 1 : 0;
        $record->classes = $model->classes;
        $record->elementType = $model->elementType;
        $record->elementId = $model->elementId;
        $record->url = $model->url;

        $save = $record->save();
        $nav = Navigate::$plugin->navigate->getNavigationById($record->navId);
        $this->setNodeCache($record->navId, $record->siteId);

        if (!$save) {
            Craft::getLogger()->log($record->getErrors(), LOG_ERR, 'navigate');
        }
        return $record;
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

        $nodes = $this->getNodesStructureByNavIdAndSiteById($record->navId, $record->siteId, true);

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
        $this->setNodeCache($record->navId, $record->siteId);
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
        $this->setNodeCache($record->navId, $record->siteId);
        return $result;
    }

}
