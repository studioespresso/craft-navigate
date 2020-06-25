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

use Craft;
use craft\helpers\Json;
use craft\models\Site;
use craft\web\Controller;
use studioespresso\navigate\models\NavigationModel;
use studioespresso\navigate\Navigate;
use yii\web\NotFoundHttpException;

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
        $data['settings'] = Navigate::$plugin->getSettings();
        $data['navigations'] = Navigate::$plugin->navigate->getAllNavigationForUser();
        return $this->renderTemplate('navigate/_index', $data);
    }

    public function actionAdd()
    {
        return $this->renderTemplate('navigate/_settings');
    }

    public function actionSave()
    {
        $this->requirePostRequest();
        if (isset(Craft::$app->request->getBodyParams()['data']['id'])) {
            $model = Navigate::$plugin->navigate->getNavigationById(Craft::$app->request->getBodyParams()['data']['id']);
        } else {
            $model = new NavigationModel();
        }

        $model->setAttributes(Craft::$app->request->getBodyParams()['data']);
        if (!$model->validate()) {
            return $this->renderTemplate('navigate/_settings', [
                'navigation' => $model,
                'errors' => $model->getErrors(),
                'sources' => Navigate::$plugin->nodes->types,
            ]);
        } else {
            Navigate::getInstance()->navigate->saveNavigation($model);
            return $this->redirectToPostedUrl();

        }

    }

    public function actionEdit($navId = null, $siteHandle = null)
    {
        if (!$siteHandle) {
            $navigation = Navigate::getInstance()->navigate->getNavigationById($navId);
            $sites = $this->getEditAbleSites($navigation);
            $firstSite = reset($sites);
            if(!$firstSite) {
                throw new NotFoundHttpException('Navigation not found', 404);
            }
            $this->redirect("navigate/edit/{$navId}/{$firstSite->handle}");
        }
        if ($navId && $siteHandle) {
            $navigation = Navigate::$plugin->navigate->getNavigationById($navId);
            $sites = Craft::$app->sites->getEditableSites();
            $site = Craft::$app->sites->getSiteByHandle($siteHandle);

            $nodeTypes = Navigate::$plugin->nodes->getNodeTypes($navigation);

            $jsOptions = implode("','", [
                $navId,
                Json::encode($nodeTypes, JSON_UNESCAPED_UNICODE),
                $navId,
                $site->id,
                $navigation->levels
            ]);
            Craft::$app->getView()->registerJs("new Craft.Navigate('" . $jsOptions . "');");

            return $this->renderTemplate('navigate/_edit', [
                'nodes' => Navigate::$plugin->nodes->getNodesByNavIdAndSiteById($navId, $site->id),
                'nodeTypes' => $nodeTypes,
                'navigation' => $navigation,
                'sites' => $this->getEditAbleSites($navigation),
                'site' => $site,
            ]);
        }
    }

    public function actionSettings($navId = null)
    {
        $data = [];
        $data['sources'] = Navigate::$plugin->nodes->types;
        $data['groups'] = $this->getSiteGroups();
        if ($navId) {
            $data['navigation'] = Navigate::$plugin->navigate->getNavigationById($navId);
        }
        return $this->renderTemplate('navigate/_settings', $data);
    }

    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        if (Navigate::$plugin->navigate->deleteNavigationById(Craft::$app->request->post('id'))) {
            // Return data
            $returnData['success'] = true;
            return $this->asJson($returnData);
        };

    }

    private function getSiteGroups()
    {
        $data = [];
        foreach (Craft::$app->getSites()->getAllGroups() as $group) {
            $data[$group->id] = $group->name;
        }

        return $data;
    }

    private function getEditAbleSites(NavigationModel $navigationModel)
    {
        if($navigationModel->enabledSiteGroups != '*' && $navigationModel->enabledSiteGroups != null) {
            $enabledForSiteGroups = json_decode($navigationModel->enabledSiteGroups);
            $enabledForSites = [];
            foreach($enabledForSiteGroups as $groupId) {
                $enabledForSites = array_merge($enabledForSites, Craft::$app->getSites()->getSitesByGroupId($groupId));
            }
        } else {
            $enabledForSites = Craft::$app->getSites()->getAllSites();
        }
        $currentUser = Craft::$app->getUser()->getIdentity();
        if(count($enabledForSites) > 1) {
            $editableSites = array_filter($enabledForSites, function ($site) use ($currentUser) {
                if ($currentUser->can("editSite:{$site->uid}")) {
                    return true;
                }
                return false;
            });
        } elseif(count($enabledForSites)) {
            if($currentUser->can('accessPlugin-navigate')) {
                $editableSites = Craft::$app->getSites()->getAllSites();
            }
        } else {
            if($currentUser->can('accessPlugin-navigate')) {
                $editableSites = Craft::$app->getSites()->getAllSites();
            }
        }
        return $editableSites;
    }
}
