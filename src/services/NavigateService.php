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

use craft\events\ConfigEvent;
use craft\helpers\StringHelper;
use studioespresso\navigate\models\NavigationModel;
use studioespresso\navigate\Navigate;

use Craft;
use craft\base\Component;
use studioespresso\navigate\records\NavigationRecord;
use yii\web\NotFoundHttpException;

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

    const CONFIG_NAVIGATE_KEY = 'navigate_navs';

    public function getAllNavigations() {
        return NavigationRecord::find()->all();
    }

    public function getNavigationById($id) {
        $record = NavigationRecord::findOne([
            'id' => $id
        ]);
        return new NavigationModel($record->getAttributes());
    }

    public function getNavigationByHandle($handle) {
        if(Craft::$app->cache->exists('navigate_nav_'.$handle)) {
            return Craft::$app->cache->get('navigate_nav_'.$handle);
        }
        return NavigationRecord::findOne([
            'handle' => $handle
        ]);
    }

    public function deleteNavigationById($id) {
        $record = NavigationRecord::findOne([
            'id' => $id
        ]);
        if($record) {
            Navigate::$plugin->nodes->deleteNodesByNavId($record);
            Craft::$app->cache->delete('navigate_nav_' . $record->handle);
            if($record->softDelete()) {
                return 1;
           };
        }
    }

    public function saveNavigation(NavigationModel $model) {
        $isNew = !$model->id;
        if($isNew){
            $navigationUid = StringHelper::UUID();
        } else {
            $navigationRecord = NavigationRecord::findOne( [
                'id' => $model->id
            ]);
            if(!$navigationRecord) {
                throw new NotFoundHttpException('Navigation not found', 404);
            }

            $navigationUid = $navigationRecord->uid;
        }

        $configData = [
            'title' => $model->title,
            'handle' => $model->handle,
            'levels' => $model->levels,
            'adminOnly' => $model->adminOnly ? 1 : 0,
            'allowedSources' => $model->allowedSources,
        ];

        $projectConfig = Craft::$app->getProjectConfig();
        $configPath = self::CONFIG_NAVIGATE_KEY . '.' . $navigationUid;
        $projectConfig->set($configPath, $configData);

    }

    public function handleChangedNavigation(ConfigEvent $event) {

        $data = $event->newValue;
        $record = NavigationRecord::findOne(['uid' => $event->tokenMatches[0]]) ?? new NavigationRecord();

        $record->title = $data['title'];
        $record->handle = $data['handle'];
        $record->levels = $data['levels'];
        $record->adminOnly = $data['adminOnly'];
        $record->allowedSources = $data['allowedSources'];
        if(!$record->uid) {
            $record->uid = $event->tokenMatches[0];
        }

        if ( ! $record->save() ) {
            Craft::getLogger()->log($record->getErrors(), LOG_ERR, 'navigate');
        }

//        Craft::$app->cache->add('navigate_nav_' . $record->handle, $record);
    }
}
