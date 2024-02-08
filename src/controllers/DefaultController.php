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
use craft\helpers\Cp;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use studioespresso\navigate\models\NavigationModel;
use studioespresso\navigate\Navigate;

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
        $data['navigations'] = Navigate::getInstance()->navigate->getAllNavigationForUser();
        return $this->renderTemplate('navigate/_index', $data);
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
        }

        $redirect = Craft::$app->getRequest()->getValidatedBodyParam('redirect');
        Navigate::getInstance()->navigate->saveNavigation($model);
        return $this->asSuccess("'{$model->title}' saved", [], $redirect);
    }

    public function actionEdit($navId = null)
    {
        if ($navId) {
            $navigation = Navigate::$plugin->navigate->getNavigationById($navId);
            $sites = Craft::$app->sites->getEditableSites();

            $site = Craft::$app->getSites()->getPrimarySite();

            $siteParam = $this->request->getQueryParam('site');
            if ($siteParam) {
                $site = Craft::$app->sites->getSiteByHandle($siteParam);
            }

            $nodeTypes = Navigate::$plugin->nodes->getNodeTypes($navigation);


            $jsOptions = implode("','", [
                $navId,
                Json::encode($nodeTypes),
                $navId,
                $site->id,
                $navigation->levels,
            ]);

            $settings = Navigate::getInstance()->getSettings();

            Craft::$app->getView()->registerJs("new Craft.Navigate('" . $jsOptions . "');");

            return $this->asCpScreen()
                ->title($navigation->title ?? "New navigation")
                ->addCrumb(Craft::t('navigate', "Navigations"), 'navigate')
                ->crumbs([
                    [
                        'label' => $settings->pluginLabel,
                        'url' => UrlHelper::cpUrl('navigate'),
                    ],
                    [
                        'label' => $site->name,
                        'menu' => [
                            'label' => Craft::t('site', 'Select site'),
                            'items' => Cp::siteMenuItems($sites, $site),
                        ],
                    ],
                ])
                ->metaSidebarTemplate('navigate/_edit/_sidebar', [
                    'navigation' => $navigation,
                ])
                ->contentTemplate('navigate/_edit/_content', [
                    'nodes' => Navigate::$plugin->nodes->getNodesByNavIdAndSiteById($navId, $site->id),
                    'nodeTypes' => $nodeTypes,
                    'navigation' => $navigation,
                    'site' => $site,
                    'sites' => $this->getEditAbleSites($navigation),
                ]);
        }
    }

    public function actionSettings($navId = null)
    {
        $data = [
            'sources' => Navigate::$plugin->nodes->types,
            'groups' => $this->getSiteGroups(),
        ];
        if ($navId) {
            $navigation = Navigate::$plugin->navigate->getNavigationById($navId);
            $data['navigation'] = $navigation;
        }

        $settings = Navigate::$plugin->getSettings();

        return $this->asCpScreen()
            ->title($navId ? $navigation->title : Craft::t('navigate', 'New navigation'))
            ->addCrumb(Craft::t('navigate', $settings->pluginLabel), 'navigate')
            ->action('navigate/default/save')
            ->redirectUrl(UrlHelper::cpUrl('navigate'))
            ->contentTemplate('navigate/_add/_content', $data);
    }

    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        if (Navigate::$plugin->navigate->deleteNavigationById(Craft::$app->request->post('id'))) {
            return $this->asJson(['success' => true]);
        }
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
        if ($navigationModel->enabledSiteGroups != '*' && $navigationModel->enabledSiteGroups != null) {
            $enabledForSiteGroups = json_decode($navigationModel->enabledSiteGroups);
            $enabledForSites = [];
            foreach ($enabledForSiteGroups as $groupId) {
                $enabledForSites = array_merge($enabledForSites, Craft::$app->getSites()->getSitesByGroupId($groupId));
            }
        } else {
            $enabledForSites = Craft::$app->getSites()->getAllSites();
        }

        $editableSites = [];
        $currentUser = Craft::$app->getUser()->getIdentity();
        if (count($enabledForSites) > 1) {
            $editableSites = array_filter($enabledForSites, function($site) use ($currentUser) {
                if ($currentUser->can("editSite:{$site->uid}")) {
                    return true;
                }
                return false;
            });
        } elseif (count($enabledForSites)) {
            if ($currentUser->can('accessPlugin-navigate')) {
                $editableSites = $enabledForSites;
            }
        } else {
            if ($currentUser->can('accessPlugin-navigate')) {
                $editableSites = Craft::$app->getSites()->getAllSites();
            }
        }
        return $editableSites;
    }
}
