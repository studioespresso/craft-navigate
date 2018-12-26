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
use craft\events\ConfigEvent;
use craft\helpers\StringHelper;
use studioespresso\navigate\models\NavigationModel;
use studioespresso\navigate\Navigate;
use studioespresso\navigate\records\NavigationRecord;
use yii\bootstrap\Nav;

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

    public function getAllNavigations()
    {
        return NavigationRecord::find()->all();
    }

    public function getNavigationById($id)
    {
        $record = NavigationRecord::findOne([
            'id' => $id
        ]);
        return new NavigationModel($record->getAttributes());
    }

    public function getNavigationByHandle($handle)
    {
        if (Craft::$app->cache->exists('navigate_nav_' . $handle)) {
            return Craft::$app->cache->get('navigate_nav_' . $handle);
        }
        return NavigationRecord::findOne([
            'handle' => $handle
        ]);
    }

    public function deleteNavigationById($id)
    {
        $record = NavigationRecord::findOne([
            'id' => $id
        ]);
        if ($record) {
            Navigate::$plugin->nodes->deleteNodesByNavId($record);
            Craft::$app->cache->delete('navigate_nav_' . $record->handle);
            if ($record->delete()) {
                return 1;
            };
        }
    }

    public function handleAddNavigation(ConfigEvent $event)
    {
        $record = NavigationRecord::findOne([
            'uid' => $event->tokenMatches[0]
        ]);
        if (!$record) {
            $record = new NavigationRecord();
        }
        $record->title = $event->newValue['title'];
        $record->handle = $event->newValue['handle'];
        $record->levels = $event->newValue['levels'];
        $record->adminOnly = $event->newValue['adminOnly'];
        $record->allowedSources = $event->newValue['allowSources'];

        $save = $record->save();
        if (!$save) {
            Craft::getLogger()->log($record->getErrors(), LOG_ERR, 'navigate');
            if (!$record->validate()) {
                return false;
            }
        }
    }

    public function saveNavigation(NavigationModel $model)
    {
        $record = false;
        if (isset($model->id)) {
            $record = NavigationRecord::findOne([
                'id' => $model->id
            ]);
        }

        if (!$record) {
            $record = new NavigationRecord();
            $record->uid = StringHelper::UUID();
        }

        $record->title = $model->title;
        $record->handle = $model->handle;
        $record->levels = $model->levels;
        $record->adminOnly = $model->adminOnly ? 1 : 0;
        $record->allowedSources = $model->allowedSources;

        if (!$record->validate()) {
            return false;
        }

        $path = "navigate.nav.{$record->uid}";
        Craft::$app->projectConfig->set($path, [
            'title' => $record->title,
            'handle' => $record->handle,
            'levels' => $record->levels,
            'adminOnly' => $record->adminOnly,
            'allowSources' => $record->allowedSources
        ]);

//        Craft::$app->cache->add('navigate_nav_' . $record->handle, $record);
        return true;
    }
}
