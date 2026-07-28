<?php
/**
 * Navigate plugin for Craft CMS
 *
 * @link      https://studioespresso.co
 * @copyright Copyright (c) 2018 Studio Espresso
 */

namespace studioespresso\navigate\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use craft\helpers\Json;
use studioespresso\navigate\models\NavigationModel;
use studioespresso\navigate\Navigate;
use yii\console\ExitCode;

/**
 * Manage Navigate navigations from the command line.
 */
class NavigationsController extends Controller
{
    /**
     * @var bool Output machine-readable JSON instead of a human-readable list.
     */
    public bool $json = false;

    /**
     * @var int Menu depth. 1 = flat; a value > 1 allows nested child items.
     */
    public int $levels = 1;

    /**
     * @var string Allowed item sources: "*" for all, or a comma-separated list of
     * entry, url, asset, category, heading.
     */
    public string $allowedSources = '*';

    /**
     * @var string Site groups the navigation is enabled for: "*" for all, or a
     * comma-separated list of site-group ids.
     */
    public string $siteGroups = '*';

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);
        $options[] = 'json';
        if ($actionID === 'create') {
            $options[] = 'levels';
            $options[] = 'allowedSources';
            $options[] = 'siteGroups';
        }
        return $options;
    }

    /**
     * List all navigations (id, handle, title, levels).
     */
    public function actionList(): int
    {
        $rows = [];
        foreach (Navigate::getInstance()->navigate->getAllNavigations() as $nav) {
            $rows[] = [
                'id' => (int)$nav->id,
                'handle' => $nav->handle,
                'title' => $nav->title,
                'levels' => (int)$nav->levels,
            ];
        }

        if ($this->json) {
            $this->stdout(Json::encode($rows) . PHP_EOL);
            return ExitCode::OK;
        }

        if (!$rows) {
            $this->stdout('No navigations found.' . PHP_EOL);
            return ExitCode::OK;
        }

        foreach ($rows as $row) {
            $this->stdout("#{$row['id']}  ", Console::FG_YELLOW);
            $this->stdout($row['handle'], Console::FG_GREEN);
            $this->stdout("  {$row['title']}  (levels: {$row['levels']})" . PHP_EOL);
        }
        return ExitCode::OK;
    }

    /**
     * Create a navigation.
     *
     * Example:
     *   ./craft navigate/navigations/create "Main menu" mainMenu --levels=2
     *
     * @param string $title The navigation's title.
     * @param string $handle The navigation's handle (must be unique).
     */
    public function actionCreate(string $title, string $handle): int
    {
        $model = new NavigationModel();
        $model->title = $title;
        $model->handle = $handle;
        $model->levels = $this->levels;
        $model->allowedSources = $this->allowedSources === '*'
            ? '*'
            : Json::encode(array_map('trim', explode(',', $this->allowedSources)));
        $model->enabledSiteGroups = $this->siteGroups === '*'
            ? '*'
            : Json::encode(array_map('trim', explode(',', $this->siteGroups)));
        $model->adminOnly = false;

        if (!$model->validate()) {
            $this->stderr('Could not create navigation:' . PHP_EOL, Console::FG_RED);
            foreach ($model->getFirstErrors() as $error) {
                $this->stderr("  - {$error}" . PHP_EOL, Console::FG_RED);
            }
            return ExitCode::DATAERR;
        }

        if (!Navigate::getInstance()->navigate->saveNavigation($model)) {
            $this->stderr('Failed to save navigation.' . PHP_EOL, Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // saveNavigation() returns a bool and writes through project config,
        // so re-fetch (skipping the cache) to get the new record and its id.
        $saved = Navigate::getInstance()->navigate->getNavigationByHandle($handle, false);

        if ($this->json) {
            $this->stdout(Json::encode([
                'id' => (int)$saved->id,
                'handle' => $saved->handle,
                'title' => $saved->title,
                'levels' => (int)$saved->levels,
            ]) . PHP_EOL);
            return ExitCode::OK;
        }

        $this->stdout('Created navigation ', Console::FG_GREEN);
        $this->stdout("'{$saved->title}'");
        $this->stdout(" (handle: {$saved->handle}, id: {$saved->id})." . PHP_EOL);
        $this->stdout("Add items with: ./craft navigate/nodes/add {$saved->handle} \"Item name\" --type=url --url=/" . PHP_EOL);
        return ExitCode::OK;
    }
}
