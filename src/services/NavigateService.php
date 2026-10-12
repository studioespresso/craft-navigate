<?php

namespace studioespresso\navigate\services;

use CraftCms\Cms\Cms;
use CraftCms\Cms\ProjectConfig\Events\ConfigEvent;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\DependencyAwareCache\Dependency\TagDependency;
use CraftCms\DependencyAwareCache\Facades\DependencyCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use studioespresso\navigate\records\NavigationRecord;

use function CraftCms\Cms\currentUser;

/**
 * Navigations, stored in project config under `navigate.nav.{uid}` and mirrored to `navigate_navigations`.
 */
class NavigateService
{
    public const NAVIGATE_CACHE = 'navigate_cache';

    public const NAVIGATE_CACHE_NAV = 'navigate_cache_nav';

    public const CONFIG_PATH = 'navigate.nav';

    /** @return Collection<int, NavigationRecord> */
    public function getAllNavigations(): Collection
    {
        return NavigationRecord::query()->orderBy('title')->get();
    }

    /**
     * The navigations the current user can edit a site of.
     *
     * @return Collection<int, NavigationRecord>
     */
    public function getAllNavigationForUser(): Collection
    {
        return $this->getAllNavigations()->filter(fn (NavigationRecord $nav) => $this->getEditableSites($nav)->isNotEmpty())->values();
    }

    public function getNavigationById(int $id): ?NavigationRecord
    {
        return NavigationRecord::query()->find($id);
    }

    public function getNavigationByHandle(string $handle, bool $fromCache = true): ?NavigationRecord
    {
        if (! $fromCache || Cms::config()->devMode) {
            return NavigationRecord::query()->where('handle', $handle)->first();
        }

        $key = self::NAVIGATE_CACHE_NAV.'_'.$handle;

        // The cache only unserializes plain data (cache.serializable_classes), so it holds the attributes
        $attributes = DependencyCache::rememberForever(
            $key,
            fn () => NavigationRecord::query()->where('handle', $handle)->first()?->getAttributes(),
            new TagDependency([self::NAVIGATE_CACHE, self::NAVIGATE_CACHE_NAV, $key]),
        );

        return $attributes ? new NavigationRecord()->newFromBuilder($attributes) : null;
    }

    /**
     * The sites a navigation can be edited in by the current user.
     *
     * @return Collection<int, Site>
     */
    public function getEditableSites(NavigationRecord $navigation): Collection
    {
        $groupIds = $navigation->enabledSiteGroupIds();
        $sites = $groupIds === null
            ? Sites::getAllSites()
            : collect($groupIds)->flatMap(fn (int $groupId) => Sites::getSitesByGroupId($groupId));

        if (! Sites::isMultiSite()) {
            return $sites->values();
        }

        return $sites->filter(fn ($site) => currentUser()?->can("editSite:$site->uid"))->values();
    }

    /**
     * Saves a navigation through project config, or straight to the database when admin changes are disallowed.
     */
    public function saveNavigation(NavigationRecord $navigation): void
    {
        $navigation->uid ??= (string) Str::uuid();

        if (Cms::config()->allowAdminChanges) {
            ProjectConfig::set(self::CONFIG_PATH.'.'.$navigation->uid, $this->configData($navigation), "Save navigation “{$navigation->handle}”");

            return;
        }

        $navigation->save();
    }

    public function deleteNavigation(NavigationRecord $navigation): void
    {
        if (Cms::config()->allowAdminChanges) {
            ProjectConfig::remove(self::CONFIG_PATH.'.'.$navigation->uid, "Delete navigation “{$navigation->handle}”");

            return;
        }

        $navigation->delete();
        $this->clearNavigationCache($navigation);
    }

    public function handleChangedNavigation(ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];
        $data = $event->newValue;

        $navigation = NavigationRecord::query()->where('uid', $uid)->first() ?? new NavigationRecord(['uid' => $uid]);
        $navigation->fill([
            'title' => $data['title'],
            'handle' => $data['handle'],
            'levels' => $data['levels'],
            'adminOnly' => $data['adminOnly'],
            'allowedSources' => $data['allowSources'],
            'enabledSiteGroups' => $data['enabledSiteGroups'],
        ]);

        if (! $navigation->save()) {
            Log::error("Couldn’t save navigation “{$uid}”.");
        }

        $this->clearNavigationCache($navigation);
    }

    public function handleDeletedNavigation(ConfigEvent $event): void
    {
        $navigation = NavigationRecord::query()->where('uid', $event->tokenMatches[0])->first();
        if ($navigation) {
            $navigation->delete();
            $this->clearNavigationCache($navigation);
        }
    }

    /** @return array{nav: array<string, array<string, mixed>>} */
    public function rebuildProjectConfig(): array
    {
        return ['nav' => NavigationRecord::query()->get()
            ->mapWithKeys(fn (NavigationRecord $nav) => [$nav->uid => $this->configData($nav)])
            ->all()];
    }

    /** @param string|string[] $tags */
    public function clearAllCaches(array|string $tags = self::NAVIGATE_CACHE): void
    {
        TagDependency::invalidate($tags);
    }

    private function clearNavigationCache(NavigationRecord $navigation): void
    {
        $this->clearAllCaches([self::NAVIGATE_CACHE_NAV.'_'.$navigation->handle, NodesService::NAVIGATE_CACHE_NODES]);
    }

    /** @return array<string, mixed> The project config representation; keys match earlier versions */
    private function configData(NavigationRecord $navigation): array
    {
        return [
            'title' => $navigation->title,
            'handle' => $navigation->handle,
            'levels' => $navigation->levels,
            'adminOnly' => $navigation->adminOnly,
            'allowSources' => $navigation->allowedSources,
            'enabledSiteGroups' => $navigation->enabledSiteGroups,
        ];
    }
}
