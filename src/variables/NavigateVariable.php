<?php
/**
 * Navigate plugin for Craft CMS 3.x
 *
 * Navigation plugin for Craft 3
 *
 * @link      https://studioespresso.co
 * @copyright Copyright (c) 2018 Studio Espresso
 */

namespace studioespresso\navigate\variables;

use studioespresso\navigate\Navigate;

use Craft;

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
     * Whatever you want to output to a Twig template can go into a Variable method.
     * You can have as many variable functions as you want.  From any Twig template,
     * call it like this:
     *
     *     {{ craft.navigate.exampleVariable }}
     *
     * Or, if your variable requires parameters from Twig:
     *
     *     {{ craft.navigate.exampleVariable(twigValue) }}
     *
     * @param null $optional
     * @return string
     */
    public function raw($navHandle)
    {
        $nodes = Navigate::$plugin->nodes->getNodesForRender($navHandle, Craft::$app->sites->getCurrentSite()->id);
        return $nodes;

    }

    public function render($navHandle, array $options = [])
    {
        $nodes = $this->raw($navHandle);

        Craft::$app->view->setTemplateMode('cp');
        $template = Craft::$app->view->renderTemplate('navigate/_render/_nav', ['nodes' => $nodes, 'classes' => $options ] );
        echo $template;

    }

}
