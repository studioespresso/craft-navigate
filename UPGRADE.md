# Upgrading to Navigate 6 (Craft 6)

This guide lists what you need to update in your project and custom code when moving to Navigate 6.

## Requirements

- Craft CMS 6
- PHP 8.5
- `craftcms/yii2-adapter` is no longer needed

## Breaking changes

### Classes and namespaces

Classes moved to Laravel-style folders. The root namespace (`studioespresso\navigate`) is unchanged.

| Before | After |
| --- | --- |
| `studioespresso\navigate\records\NavigationRecord` | `studioespresso\navigate\Models\NavigationRecord` |
| `studioespresso\navigate\records\NodeRecord` | `studioespresso\navigate\Models\NodeRecord` |
| `studioespresso\navigate\models\NodeModel` | `studioespresso\navigate\Data\NodeModel` |
| `studioespresso\navigate\models\Settings` | `studioespresso\navigate\Settings` |
| `studioespresso\navigate\variables\NavigateVariable` | `studioespresso\navigate\Twig\NavigateVariable` |
| `studioespresso\navigate\fields\NavigateField` | `studioespresso\navigate\Fields\NavigateField` |
| `studioespresso\navigate\services\NavigateService` | `studioespresso\navigate\Services\NavigateService` |
| `studioespresso\navigate\services\NodesService` | `studioespresso\navigate\Services\NodesService` |

Existing Navigate fields keep working: the old field class name, as stored in the database and project config, still resolves.

### Config file

The config file moves with Craft 6's config folder:

| Before | After |
| --- | --- |
| `config/navigate.php` | `config/craft/navigate.php` |

Run `php craft craft:setup:publish` to publish a fresh copy.

## Other changes

### Console commands

Console commands are now Artisan commands. The Craft 5 names still work as aliases, so existing scripts keep running.

| Before | After |
| --- | --- |
| `php craft navigate/navigations/list` | `php artisan navigate:navigations:list` |
| `php craft navigate/navigations/create` | `php artisan navigate:navigations:create` |
| `php craft navigate/nodes/list` | `php artisan navigate:nodes:list` |
| `php craft navigate/nodes/add` | `php artisan navigate:nodes:add` |
