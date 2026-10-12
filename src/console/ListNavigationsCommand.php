<?php

namespace studioespresso\navigate\console;

use CraftCms\Cms\Console\CraftCommand;
use Illuminate\Console\Command;
use studioespresso\navigate\Navigate;
use studioespresso\navigate\records\NavigationRecord;

class ListNavigationsCommand extends Command
{
    use CraftCommand;

    protected $signature = 'navigate:navigations:list {--json : Output JSON instead of a table}';

    protected $description = 'Lists all navigations (id, handle, title, levels).';

    protected $aliases = ['navigate/navigations/list'];

    public function handle(): int
    {
        $rows = Navigate::getInstance()->navigate->getAllNavigations()
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
