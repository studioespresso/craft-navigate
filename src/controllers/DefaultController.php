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
class DefaultController extends Controller
{

    // Public Methods
    // =========================================================================

    /**
     * Handle a request going to our plugin's index action URL,
     * e.g.: actions/navigate/default
     *
     * @return mixed
     */
    public function actionIndex()
    {
        $data = [];
        $data['navigations'] = Navigate::$plugin->navigate->getAllNavigations();
        return $this->renderTemplate('navigate/_index', $data);
    }

    public function actionAdd() {
        return $this->renderTemplate('navigate/_settings');
    }

    public function actionSave() {
        Navigate::$plugin->navigate->saveNavigation(Craft::$app->request->getBodyParams());
        return $this->redirectToPostedUrl();
    }

    public function actionEdit($navId = null) {
        if($navId) {
            $data['navigation'] = Navigate::$plugin->navigate->getNavigationById($navId);

            $nodeTypes = Navigate::$plugin->nodes->getNodeTypes($data['navigation']);

            Craft::$app->getView()->registerJs('new Craft.NavigateInput('.
                '"navigate-nodes-input", '.
                Json::encode($nodeTypes, JSON_UNESCAPED_UNICODE) . ');');


            return $this->renderTemplate('navigate/_edit', [
                'nodes' => Navigate::$plugin->nodes->getNodesByNavId($navId),
                'nodeTypes' =>$nodeTypes,
            ]);
        }
    }

    public function actionSettings($navId = null) {
        $data = [];
        $data['sources'] = [
            'entry' => 'Entry',
            'url' => 'Url',
            'asset' => 'Asset',
            'category' => 'Category',
            'email' => 'Email',
        ];
        if($navId) {
            $data['navigation'] = Navigate::$plugin->navigate->getNavigationById($navId);
        }
        return $this->renderTemplate('navigate/_settings', $data);
    }

    public function actionDelete() {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        return Navigate::$plugin->navigate->deleteNavigationById(Craft::$app->request->post('id'));

    }
}
