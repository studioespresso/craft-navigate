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
        $query->indexBy('id');
        return $query->all();
    }

    public function getNodesByNavIdAndSite(int $navId = null, $siteId) {
        $query = NodeRecord::find();
        $query->where(['navId' => $navId, 'siteId' => $siteId]);
        $query->indexBy('id');
        return $query->all();
    }

    public function getNodeById(int $navId = null) {
        $query = NodeRecord::findOne([
            'id' => $navId
        ]);
        if($query) {
            $model = new NodeModel();
            $model->setAttributes($query->getAttributes());
            return $model;
        }
    }

    public function getNodeUrl(NodeModel $node) {
        if($node->type === "url") {
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

    public function save(NodeModel $model)
    {

        $record = false;
        if (isset($model->id)) {
            $record = NodeRecord::findOne([
                'id' => $model->id
            ]);
        }
        if (!$record) {
            $record = new NodeRecord();
        }



        $record->siteId = $model->siteId;
        $record->navId = $model->navId;
        $record->name = $model->name;
        $record->type = $model->type;
        $record->elementType = $model->elementType;
        $record->elementId = $model->elementId;
        $record->url = $model->url;


        $save = $record->save();
        if (!$save) {
            Craft::getLogger()->log($record->getErrors(), LOG_ERR, 'navigate');
        }
        return $save;
    }

    public function cleanupNode($nodes, $navigation, $site)
    {

        $oldNodes = Navigate::$plugin->nodes->getNodesByNavIdAndSite($navigation, $site);
        array_walk($nodes, function ($node) use (&$oldNodes) {
            $model = new NodeModel();
            $model->setAttributes($node);
            if (array_key_exists($model->id, $oldNodes)) {
                unset($oldNodes[$model->id]);
            }
        });

        foreach ($oldNodes as $node) {
            $record = NodeRecord::findOne([
                'id' => $node->id,
            ]);
            $record->delete();
        }


    }

}
