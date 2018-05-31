<?php
/**
 * Navigate plugin for Craft CMS 3.x
 *
 * Navigation plugin for Craft 3
 *
 * @link      https://studioespresso.co
 * @copyright Copyright (c) 2018 Studio Espresso
 */

namespace studioespresso\navigate\controllers;

use craft\helpers\Json;
use studioespresso\navigate\models\NavigationModel;
use studioespresso\navigate\models\NodeModel;
use studioespresso\navigate\Navigate;

use Craft;
use craft\web\Controller;
use yii\bootstrap\Nav;

/**
 * Default Controller
 *
 * Generally speaking, controllers are the middlemen between the front end of
 * the CP/website and your plugin’s services. They contain action methods which
 * handle individual tasks.
 *
 * A common pattern used throughout Craft involves a controller action gathering
 * post data, saving it on a model, passing the model off to a service, and then
 * responding to the request appropriately depending on the service method’s response.
 *
 * Action methods begin with the prefix “action”, followed by a description of what
 * the method does (for example, actionSaveIngredient()).
 *
 * https://craftcms.com/docs/plugins/controllers
 *
 * @author    Studio Espresso
 * @package   Navigate
 * @since     0.0.1
 */
class NodesController extends Controller
{

    public function actionSave() {
        $this->requirePostRequest();

        $data = Craft::$app->request->post('data');
        if($data['nodes']) {
            foreach($data['nodes'] as $node) {
                $nodeModel = new NodeModel();
                $nodeModel->setAttributes($node);
                if($nodeModel->validate()) {
                    Navigate::$plugin->nodes->save($nodeModel);
                } else {
                    dd($nodeModel->getErrors());
                }
            }

        }

    }

}
