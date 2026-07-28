<?php
/**
 * Navigate plugin for Craft CMS
 *
 * @link      https://studioespresso.co
 * @copyright Copyright (c) 2018 Studio Espresso
 */

namespace studioespresso\navigate\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use craft\helpers\Json;
use craft\models\Site;
use studioespresso\navigate\models\NodeModel;
use studioespresso\navigate\Navigate;
use yii\console\ExitCode;

/**
 * Manage Navigate navigation items (nodes) from the command line.
 */
class NodesController extends Controller
{
    /**
     * @var bool Output machine-readable JSON instead of a human-readable list.
     */
    public bool $json = false;

    /**
     * @var string|null Site handle or id. Defaults to the primary site.
     */
    public ?string $site = null;

    /**
     * @var string Item type: url, entry, asset, category or heading.
     */
    public string $type = 'url';

    /**
     * @var string|null URL for a "url" item.
     */
    public ?string $url = null;

    /**
     * @var int|null Element id for an entry / asset / category item.
     */
    public ?int $elementId = null;

    /**
     * @var int|null Parent node id, to nest this item beneath it.
     */
    public ?int $parent = null;

    /**
     * @var bool Open the link in a new tab.
     */
    public bool $blank = false;

    /**
     * @var string|null CSS classes to apply to the item.
     */
    public ?string $classes = null;

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);
        $options[] = 'json';
        $options[] = 'site';
        if ($actionID === 'add') {
            $options[] = 'type';
            $options[] = 'url';
            $options[] = 'elementId';
            $options[] = 'parent';
            $options[] = 'blank';
            $options[] = 'classes';
        }
        return $options;
    }

    /**
     * List the items in a navigation for a site.
     *
     * Example:
     *   ./craft navigate/nodes/list mainMenu --site=default
     *
     * @param string $nav The navigation handle.
     */
    public function actionList(string $nav): int
    {
        $navigation = Navigate::getInstance()->navigate->getNavigationByHandle($nav, false);
        if (!$navigation) {
            $this->stderr("No navigation found with handle '{$nav}'." . PHP_EOL, Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $site = $this->resolveSite();
        if (!$site) {
            return ExitCode::DATAERR;
        }

        $rows = [];
        foreach (Navigate::getInstance()->nodes->getNodesStructureByNavIdAndSiteById($navigation->id, $site->id) as $node) {
            $rows[] = [
                'id' => (int)$node->id,
                'name' => $node->name,
                'type' => $node->type,
                'url' => $node->url,
                'elementId' => $node->elementId ? (int)$node->elementId : null,
                'parent' => $node->parent ? (int)$node->parent : null,
                'order' => (int)$node->order,
                'enabled' => (bool)$node->enabled,
            ];
        }

        if ($this->json) {
            $this->stdout(Json::encode($rows) . PHP_EOL);
            return ExitCode::OK;
        }

        if (!$rows) {
            $this->stdout("No items in '{$nav}' for site '{$site->handle}'." . PHP_EOL);
            return ExitCode::OK;
        }

        foreach ($rows as $row) {
            $indent = $row['parent'] ? '   └─ ' : '';
            $target = $row['type'] === 'element' ? "element #{$row['elementId']}" : $row['url'];
            $this->stdout("#{$row['id']}  ", Console::FG_YELLOW);
            $this->stdout($indent . $row['name'], Console::FG_GREEN);
            $this->stdout("  [{$row['type']}]  {$target}" . PHP_EOL);
        }
        return ExitCode::OK;
    }

    /**
     * Add an item to a navigation.
     *
     * Examples:
     *   ./craft navigate/nodes/add mainMenu "Home" --type=url --url=/
     *   ./craft navigate/nodes/add mainMenu "About" --type=entry --element-id=42
     *   ./craft navigate/nodes/add mainMenu "Products" --type=heading
     *   ./craft navigate/nodes/add mainMenu "Child" --type=url --url=/x --parent=12
     *
     * @param string $nav The navigation handle.
     * @param string $name The item's label.
     */
    public function actionAdd(string $nav, string $name): int
    {
        $navigation = Navigate::getInstance()->navigate->getNavigationByHandle($nav, false);
        if (!$navigation) {
            $this->stderr("No navigation found with handle '{$nav}'." . PHP_EOL, Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $site = $this->resolveSite();
        if (!$site) {
            return ExitCode::DATAERR;
        }

        $node = new NodeModel();
        $node->navId = $navigation->id;
        $node->siteId = $site->id;
        $node->name = $name;
        $node->parent = $this->parent;
        $node->blank = $this->blank;
        $node->classes = $this->classes;
        $node->enabled = true;

        // Map the friendly --type onto the plugin's node type (lowercase).
        // entry/asset/category all become an "element" node with an elementType.
        $type = strtolower($this->type);
        if ($type === 'url') {
            if (!$this->url) {
                $this->stderr('--url is required for a url item.' . PHP_EOL, Console::FG_RED);
                return ExitCode::USAGE;
            }
            $node->type = 'url';
            $node->url = $this->url;
        } elseif (in_array($type, ['entry', 'asset', 'category'], true)) {
            if (!$this->elementId) {
                $this->stderr("--element-id is required for a {$type} item." . PHP_EOL, Console::FG_RED);
                return ExitCode::USAGE;
            }
            $node->type = 'element';
            $node->elementType = $type;
            $node->elementId = $this->elementId;
        } elseif ($type === 'heading') {
            $node->type = 'heading';
        } else {
            $this->stderr("Unknown --type '{$this->type}'. Use: url, entry, asset, category or heading." . PHP_EOL, Console::FG_RED);
            return ExitCode::USAGE;
        }

        if ($this->parent && (int)$navigation->levels <= 1) {
            $this->stdout("Warning: navigation '{$nav}' has levels=1, so nested items won't render. Increase its levels to nest." . PHP_EOL, Console::FG_YELLOW);
        }

        if (!$node->validate()) {
            $this->stderr('Could not add item:' . PHP_EOL, Console::FG_RED);
            foreach ($node->getFirstErrors() as $error) {
                $this->stderr("  - {$error}" . PHP_EOL, Console::FG_RED);
            }
            return ExitCode::DATAERR;
        }

        $saved = Navigate::getInstance()->nodes->save($node);
        if ($saved === false) {
            $this->stderr("Failed to save item (see the 'navigate' log for details)." . PHP_EOL, Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if ($this->json) {
            $this->stdout(Json::encode([
                'id' => (int)$saved->id,
                'name' => $saved->name,
                'type' => $saved->type,
                'navId' => (int)$saved->navId,
                'siteId' => (int)$saved->siteId,
                'parent' => $saved->parent ? (int)$saved->parent : null,
            ]) . PHP_EOL);
            return ExitCode::OK;
        }

        $this->stdout('Added ', Console::FG_GREEN);
        $this->stdout("'{$saved->name}'");
        $this->stdout(" to '{$nav}' (node id: {$saved->id}, site: {$site->handle})." . PHP_EOL);
        return ExitCode::OK;
    }

    /**
     * Resolves the --site option (handle or id) to a Site, or the primary site.
     */
    private function resolveSite(): ?Site
    {
        $sites = Craft::$app->getSites();
        if (!$this->site) {
            return $sites->getPrimarySite();
        }

        $site = is_numeric($this->site)
            ? $sites->getSiteById((int)$this->site)
            : $sites->getSiteByHandle($this->site);

        if (!$site) {
            $this->stderr("No site found matching '{$this->site}'." . PHP_EOL, Console::FG_RED);
        }
        return $site;
    }
}
