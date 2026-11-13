<?php

namespace studioespresso\navigate\Console\Commands;

use CraftCms\Cms\Console\CraftCommand;
use Illuminate\Console\Command;
use studioespresso\navigate\Models\NavigationRecord;
use studioespresso\navigate\services\NavigateService;

class ListNavigationsCommand extends Command
{
    use CraftCommand;

    protected $signature = 'navigate:navigations:list {--json : Output JSON instead of a table}';

    protected $description = 'Lists all navigations (id, handle, title, levels).';

    protected $aliases = ['navigate/navigations/list'];

    public function handle(NavigateService $navigate): int
    {
        $rows = $navigate->getAllNavigations()
            ->map(fn (NavigationRecord $nav) => ['id' => $nav->id, 'handle' => $nav->handle, 'title' => $nav->title, 'levels' => $nav->levels])
            ->all();

        if ($this->option('json')) {
            $this->line(json_encode($rows));
        } elseif (! $rows) {
            $this->components->info('No navigations found.');
        } else {
            $this->table(['ID', 'Handle', 'Title', 'Levels'], $rows);
        }

        return self::SUCCESS;
    }
}
