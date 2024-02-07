<?php
/**
 * Navigate plugin for Craft CMS 3.x
 *
 * Navigation plugin for Craft 3
 *
 * @link      https://studioespresso.dev
 * @copyright Copyright (c) 2018 Studio Espresso
 */

namespace studioespresso\navigate\variables;

use Craft;
use studioespresso\navigate\Navigate;

/**
 * Navigate Variable
 *
 * Craft allows plugins to provide their own template variables, accessible from
 * the {{ craft }} global variable (e.g. {{ craft.navigate }}).
 *
 * https://craftcms.com/docs/plugins/variables
 *
 * @author    Studio Espresso
 * @package   Navigate
 * @since     0.0.1
 */
class NavigateVariable
{
    // Public Methods
    // =========================================================================
    /**
     * @param $navHandle
     * @param null $siteId since 2.6.0
     * @return mixed
     * @throws \craft\errors\SiteNotFoundException
     */
    public function raw($navHandle, $siteId = null)
    {
        if ($siteId) {
            $site = Craft::$app->getSites()->getSiteById($siteId);
            if (!$site) {
                $siteId = Craft::$app->sites->getCurrentSite()->id;
            }
        } else {
            $siteId = Craft::$app->sites->getCurrentSite()->id;
        }
        return Navigate::$plugin->nodes->getNodesForRender($navHandle, $siteId);
    }

    public function getSettings()
    {
        return Navigate::getInstance()->getSettings();
    }

    /**
     * @param $navHandle
     * @param array $options
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \craft\errors\SiteNotFoundException
     * @throws \yii\base\Exception
     * @deprecated This function will be removed in the next major release
     */
    public function render($navHandle, array $options = [])
    {
        Craft::$app->getDeprecator()->log(__CLASS__ . 'render', "The `render` template function has been deprecated and will be removed on the next major release. Please change your template to use the `raw` function.");
        $nodes = $this->raw($navHandle);

        Craft::$app->view->setTemplateMode('cp');
        $template = Craft::$app->view->renderTemplate('navigate/_render/_nav', ['nodes' => $nodes, 'classes' => $options]);
        echo $template;
    }

}
