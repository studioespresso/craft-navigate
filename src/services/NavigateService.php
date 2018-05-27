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

use studioespresso\navigate\Navigate;

use Craft;
use craft\base\Component;
use studioespresso\navigate\records\NavigationRecord;

/**
 * NavigateService Service
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
class NavigateService extends Component
{

    public function getAllNavigations() {
        return NavigationRecord::find()->all();
    }

    public function getNavigationById($id) {
        return NavigationRecord::findOne([
            'id' => $id
        ]);
    }

    public function deleteNavigationById($id) {
        $record = NavigationRecord::findOne([
            'id' => $id
        ]);
        if($record->delete()) {
            return true;
        };
    }

    public function saveNavigation($data) {
        $record = false;
        if(isset($data['id'])) {
            $record = NavigationRecord::findOne( [
                'id' => $data['id']
            ]);
        }
        if(!$record){
            $record = new NavigationRecord();
        }

        $record->title = $data['title'];
        $record->handle = $data['handle'];
        $record->siteId = 1;

        $save = $record->save();
        if ( ! $save ) {
            Craft::getLogger()->log( $record->getErrors(), LOG_ERR, 'navigate' );
        }
        return $save;
    }
}
