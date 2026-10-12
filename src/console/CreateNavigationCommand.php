<?php

namespace studioespresso\navigate\console;

use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Cms\Validation\Rules\HandleRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use studioespresso\navigate\Navigate;
use studioespresso\navigate\records\NavigationRecord;
use studioespresso\navigate\services\NodesService;

class CreateNavigationCommand extends Command
{
    use CraftCommand;

    protected $signature = 'navigate:navigations:create
        {title : The navigation’s title}
        {handle : The navigation’s handle, must be unique}
        {--levels=1 : Menu depth. 1 = flat; more allows nested items}
        {--allowed-sources= : Comma-separated list of entry, url, asset, heading (default: all)}
        {--site-groups= : Comma-separated list of site group IDs (default: all)}
        {--json : Output JSON}';

    protected $description = 'Creates a navigation.';

    protected $aliases = ['navigate/navigations/create'];

    public function handle(): int
    {
        $values = [
            'title' => $this->argument('title'),
            'handle' => $this->argument('handle'),
            'levels' => $this->option('levels'),
            'allowedSources' => $this->listOption('allowed-sources'),
            'enabledSiteGroups' => $this->listOption('site-groups'),
        ];
        $validator = Validator::make($values, [
            'title' => ['required', 'string', 'max:255'],
            'handle' => ['required', 'string', 'max:64', new HandleRule, Rule::unique(NavigationRecord::class, 'handle')],
            'levels' => ['required', 'integer', 'min:1', 'max:5'],
            'allowedSources' => ['nullable', 'array'],
            'allowedSources.*' => [Rule::in(array_keys(NodesService::TYPES))],
            'enabledSiteGroups' => ['nullable', 'array'],
            'enabledSiteGroups.*' => ['integer'],
        ], [
            'allowedSources.*.in' => '“:input” isn’t a node type, use any of '.implode(', ', array_keys(NodesService::TYPES)).'.',
            'enabledSiteGroups.*.integer' => 'Site groups must be IDs.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::INVALID;
        }

        Navigate::getInstance()->navigate->saveNavigation(new NavigationRecord([
            'title' => $values['title'],
            'handle' => $values['handle'],
            'levels' => (int) $values['levels'],
            'adminOnly' => false,
            'allowedSources' => $values['allowedSources'] === null ? '*' : json_encode($values['allowedSources']),
            'enabledSiteGroups' => $values['enabledSiteGroups'] === null ? '*' : json_encode(array_map('intval', $values['enabledSiteGroups'])),
        ]));

        // Saved through project config, so fetch the record to get its ID
        $saved = Navigate::getInstance()->navigate->getNavigationByHandle($values['handle'], false);
        if (! $saved) {
            $this->components->error('Couldn’t save the navigation.');

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode(['id' => $saved->id, 'handle' => $saved->handle, 'title' => $saved->title, 'levels' => $saved->levels]));
        } else {
            $this->components->info("Created navigation “{$saved->title}” (handle: {$saved->handle}, id: {$saved->id}).");
            $this->line("Add items with: php artisan navigate:nodes:add {$saved->handle} \"Item name\" --type=url --url=/");
        }

        return self::SUCCESS;
    }

    /** @return list<string>|null null for all (empty or "*"), else the comma-separated values */
    private function listOption(string $name): ?array
    {
        $value = trim((string) $this->option($name));

        return in_array($value, ['', '*'], true) ? null : array_values(array_filter(array_map('trim', explode(',', $value)), 'strlen'));
    }
}
