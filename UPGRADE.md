# Upgrading to Navigate 6 (Craft 6)

This guide lists what you need to update in your project and custom code when moving to Navigate 6.

## Requirements

- Craft CMS 6
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

Existing Navigate fields keep working: the old field class name, as stored in the database and project config, still resolves.
