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

use studioespresso\navigate\models\NodeModel;
use studioespresso\navigate\Navigate;

use Craft;
use craft\base\Component;
use studioespresso\navigate\records\NavigationRecord;
use studioespresso\navigate\records\NodeRecord;

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
        return NodeRecord::findAll([
            'navId' => $navId
        ]);
    }

    public function getNodeTypes(NavigationRecord $navigation)
    {
        $nodeTypes = [];
        if($navigation->allowedSources === "*") {
            foreach($this->types as $handle => $title) {
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

    public function save(NodeModel $model) {

        $record = false;
        if(isset($model->id)) {
            $record = NodeRecord::findOne( [
                'id' => $model->id
            ]);
        }
        if(!$record){
            $record = new NodeRecord();
        }

        $record->type = $model->type;
        $record->enabled = $model->enabled;
        $record->siteId = 1;

        $save = $record->save();
        if ( ! $save ) {
            Craft::getLogger()->log( $record->getErrors(), LOG_ERR, 'navigate' );
        }
        return $save;
    }
}
