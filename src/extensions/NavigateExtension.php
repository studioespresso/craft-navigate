<?php
/**
 * Navigate plugin for Craft CMS 3.x
 *
 * Navigation plugin for Craft 3
 *
 * @link      https://studioespresso.co
 * @copyright Copyright (c) 2018 Studio Espresso
 */

namespace studioespresso\navigate\extensions;

use Craft;
use Twig\Extension\AbstractExtension;

/**
 * Navigate Extension

 *
 * https://craftcms.com/docs/plugins/variables
 *
 * @author    Studio Espresso
 * @package   Navigate
 * @since     0.0.1
 */
class NavigateExtension extends AbstractExtension
{
    // Public Methods
    // =========================================================================
    public function getFunctions()
    {
        return [
            new \Twig\TwigFunction('parse_url', [$this, 'parseUrl']),
        ];
    }

    public function parseUrl($url) {
        return parse_url($url);
    }

}
