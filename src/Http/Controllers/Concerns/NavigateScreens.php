<?php

namespace studioespresso\navigate\Http\Controllers\Concerns;

use CraftCms\Cms\Cms;
use CraftCms\Cms\Cp\Data\ActionItem;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Url;
use Illuminate\Http\Request;
use studioespresso\navigate\Models\NavigationRecord;
use studioespresso\navigate\Navigate;

use function CraftCms\Cms\currentUser;

/**
 * Lookups and access rules shared by the Navigate controllers.
 */
trait NavigateScreens
{
    protected function navigation(int|string|null $id): NavigationRecord
    {
        return $this->navigate->getNavigationById((int) $id) ?? abort(404, 'Navigation not found');
    }

    /** The site picked with `?site=`, limited to the ones the user may edit the navigation in */
    protected function site(Request $request, NavigationRecord $navigation, string $param = 'site'): Site
    {
        $sites = $this->navigate->getEditableSites($navigation);
        abort_if($sites->isEmpty(), 403);

        $value = $request->input($param);

        return $sites->first(fn (Site $site) => $value !== null && ($site->handle === $value || (string) $site->id === (string) $value))
            ?? $sites->firstWhere('primary', true)
            ?? $sites->first();
    }

    /** Whether the user can add and delete navigations */
    protected function canManageNavigations(): bool
    {
        $settings = $this->settings;

        return ((currentUser()?->isAdmin() ?? false) || $settings->anyoneCanAdd)
            && (Cms::config()->allowAdminChanges || $settings->allowWhenReadOnly);
    }

    /** Whether the user can change a navigation's settings */
    protected function canEditSettings(NavigationRecord $navigation): bool
    {
        return (Cms::config()->allowAdminChanges || $this->settings->allowWhenReadOnly)
            && (! $navigation->adminOnly || (currentUser()?->isAdmin() ?? false));
    }

    /** @return list<ActionItem> */
    protected function crumbs(): array
    {
        return [new ActionItem()->label($this->settings->pluginLabel ?: 'Navigate')->href(Url::cpUrl('navigate'))];
    }

    protected function registerAssets(): void
    {
        $plugin = app(Navigate::class);
        foreach (['css/navigate.css' => 'cssFile', 'js/structure.js' => 'jsFile'] as $file => $method) {
            $source = $plugin->getResourcesPath().'/dist/'.$file;
            if (! file_exists($source)) {
                continue;
            }
            $url = $plugin->asset('dist/'.$file).'?v='.filemtime($source);
            $method === 'jsFile' ? HtmlStack::jsFile($url, ['defer' => true]) : HtmlStack::cssFile($url);
        }
    }
}
