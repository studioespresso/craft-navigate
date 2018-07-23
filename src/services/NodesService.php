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

use studioespresso\navigate\models\NavigationModel;
use studioespresso\navigate\models\NodeModel;
use studioespresso\navigate\Navigate;

use Craft;
use craft\base\Component;
use studioespresso\navigate\records\NavigationRecord;
use studioespresso\navigate\records\NodeRecord;
use Twig\Node\Node;
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

    private $nodes;

    public $types = [
        'entry' => 'Entry',
        'url' => 'Url',
        'asset' => 'Asset',
        'category' => 'Category'
    ];


    public function getNodesByNavId(int $navId = null)
    {
        $query = NodeRecord::find();
        $query->where(['navId' => $navId]);
        $query->orderBy('order');
        return $query->all();
    }


    public function getNodesForRender($navHandle, $site)
    {
        $nav = Navigate::$plugin->navigate->getNavigationByHandle($navHandle);
        if(!$nav) {
            return false;
        }

        $nodes = $this->getNodesByNavIdAndSiteById($nav->id, $site, false, true);
        $nodes = $this->parseNodesForRender($nodes);
        return $nodes;
    }

    private function parseNodesForRender(array $nodes): array
    {
        $data = [];
        foreach($nodes as $node) {
            /* @var $node NodeModel*/
            $data[$node->order] = $this->parseNode($node);
        }
        return $data;
    }

    private function parseNode(NodeModel $node) {
        if($node->type === 'element') {
            $element = Craft::$app->elements->getElementById($node->elementId);
            if($element) {
                $node->url = $element->getUrl();
                $node->slug = $element->uri;
            }
        }

        $node->children = $node->getChildren();
        if($node->children) {
            foreach($node->children as $child) {
                $node->children[$child->order] = $this->parseNode($child);
            }
        }

        return $node;
    }

    public function getChildrenByNode(NodeModel $node)
    {
        $data = [];
        $query = NodeRecord::find();
        $query->where(['navId' => $node->navId, 'siteId' => $node->siteId, 'parent' => $node->id]);
        $query->orderBy('order');
        foreach ($query->all() as $record) {
            $model = new NodeModel();
            $model->setAttributes($record->getAttributes());
            $data[$model->order] = $model;
        }
        return $data;
    }

    public function getNodesByNavIdAndSiteById(int $navId = null, $siteId, $refresh = false, $excludeDisabled = false)
    {
        if (!$refresh && isset($this->nodes[$navId])) {
            return $this->nodes[$navId];
        }

        $query = NodeRecord::find();
        $query->where(['navId' => $navId, 'siteId' => $siteId, 'parent' => null]);
        if($excludeDisabled) {
            $query->andWhere(['enabled' => 1]);
        }
        $query->orderBy('parent ASC, order ASC');
        $data = [];
        foreach ($query->all() as $record) {
            $model = new NodeModel();
            $model->setAttributes($record->getAttributes());

            $data[$model->id] = $model;

        }
        $this->nodes[$navId] = $data;
        return $this->nodes[$navId];

    }

    public function getNodesStructureByNavIdAndSiteById(int $navId = null, $siteId, $refresh = false)
    {
        if (!$refresh && isset($this->nodes[$navId])) {
            return $this->nodes[$navId];
        }

        $query = NodeRecord::find();
        $query->where(['navId' => $navId, 'siteId' => $siteId]);
        $query->orderBy('parent ASC, order ASC');
        $data = [];
        foreach ($query->all() as $record) {
            $model = new NodeModel();
            $model->setAttributes($record->getAttributes());

            $data[$model->id] = $model;

        }
        $this->nodes[$navId] = $data;
        return $this->nodes[$navId];

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
        $record = false;

        if (isset($model->id)) {
            if(NodeRecord::deleteAll([
                'id' => $model->id
            ])) {
                NodeRecord::deleteAll([
                    'parent' => $model->id
                ]);
                return true;
            };
        } else {
            throw new NotFoundHttpException('Node not found', 404);
        }

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

        if (!$save) {
            Craft::getLogger()->log($record->getErrors(), LOG_ERR, 'navigate');
        }


        return $record;
    }

    public function move(NodeModel $node, $parent, $previousId)
    {
        $record = NodeRecord::findOne(['id' => $node->id]);

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
        return $record->save();

    }

}
