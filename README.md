# Navigate for Craft CMS

Menu's & navigations made easy.

![Easy Address Field](https://www.studioespresso.co/assets/Navigate-Github-Banner.png)


## Requirements

This plugin works with Craft CMS 3.x, 4.x and 5.x

## Installation

To install the plugin, follow these instructions.

        cd /path/to/project
        composer require studioespresso/craft-navigate
        ./craft install/plugin navigate


## Templating

### craft.navigate.raw

`craft.navigate.raw` will give you an array of the items you added to the navigation, with children for each if you have those. 
For each node you will have access to:

```twig
{% set nav = craft.navigate.raw('navHandle') %}
{% for node in nav %}
	{{ node.name }}
	{{ node.url }}
	{{ node.classes }}
	{{ node.blank }}
	{{ node.children }}
{% endfor %} 
```

This gives you complete control over the HTML & CSS used to display your navigation.

Here's an example of how to show a navigation and it's possible children using a twig macro:

```twig
{% import _self as macros %}
{% macro renderNode(node) %}
    {% import _self as macros %}
    <li class="{% if node.classes|length %}{{ node.classes }}{% endif %}">
        <a  href="{{ node.url }}" class="{% if node.active %}active{% endif %}" 
        {% if node.blank %}target="_blank" rel="noopener"{% endif %}>{{ node.name }}
        </a>
        {% if node.children|length %}
            <ul>
            {% for child in node.children %}
                {{ macros.renderNode(child) }}
            {% endfor %}
            </ul>
        {% endif %}
    </li>
{% endmacro %}

<div>
    <ul>
    {% for node in craft.navigate.raw('navHandle') %}
        {{ macros.renderNode(node) }}
    {% endfor %}
    </ul>
</div>
```

## Headings

Since 2.3.0, headings can be added to make it easier to build larger navigations with mulitple sections or columns. To make full use of this you check check ``node.type == "heading"`` and add the html you need in the condition. 

These only work on multiple levels (eg: 3 headings on the top level, each with navigation items on deeper levels). 

## Caching

Pages (and `{% cache %}` blocks) that render a navigation get the `navigate` and `navigate:{handle}:{siteId}` cache tags. Saving, moving or deleting a node invalidates that navigation's tag, changes to linked elements invalidate `navigate`. Static/proxy cache purgers listening to Craft's `ElementCachesInvalidated` event get the same tags.

## Console commands

Navigate ships console commands for building navigations from the command line — handy for
seeding, scripting, and AI-assisted prototyping. All support `--json` for machine-readable
output. The Craft 5 names (`php craft navigate/nodes/add …`) still work as aliases.

```bash
php artisan navigate:navigations:list [--json]
php artisan navigate:navigations:create "<title>" <handle> [--levels=2] [--allowed-sources=entry,url] [--site-groups=1,2]

php artisan navigate:nodes:list <navHandle> [--site=<handle|id>] [--json]
php artisan navigate:nodes:add <navHandle> "<name>" --type=url --url=/path
php artisan navigate:nodes:add <navHandle> "<name>" --type=entry --element-id=42
php artisan navigate:nodes:add <navHandle> "<name>" --type=heading
php artisan navigate:nodes:add <navHandle> "<name>" --type=url --url=/x --parent=<nodeId>   # nest
```

`--type` accepts `url`, `entry`, `asset` or `heading`. `entry`/`asset` require `--element-id`; `url` requires `--url`.
Other `add` options: `--site`, `--parent`, `--blank`, `--classes`. Run `php artisan help navigate:nodes:add` for the full list.

## Programmatic API

Navigations and their items can also be managed in code — inside migrations, modules, or `php artisan tinker`.
There are no GraphQL mutations.

- `Navigate::getInstance()->navigate` — `NavigateService`, manages **navigations** (`NavigationRecord`).
- `Navigate::getInstance()->nodes` — `NodesService`, manages **nodes** (`NodeRecord`).

Agents working in this repo should also read [`AGENTS.md`](AGENTS.md).

```php
use studioespresso\navigate\Navigate;
use studioespresso\navigate\records\NavigationRecord;
use studioespresso\navigate\records\NodeRecord;

Navigate::getInstance()->navigate->saveNavigation(new NavigationRecord([
    'title' => 'Main menu',
    'handle' => 'mainMenu',       // unique handle
    'levels' => 2,                // 1 = flat; > 1 allows nested children
    'allowedSources' => '*',      // '*' or a JSON array, e.g. '["entry","url"]'
    'enabledSiteGroups' => '*',   // '*' or a JSON array of site group IDs
    'adminOnly' => false,
]));
// Saved through project config, so fetch it again for its ID
$navId = Navigate::getInstance()->navigate->getNavigationByHandle('mainMenu', false)->id;

$home = new NodeRecord(['navId' => $navId, 'siteId' => $siteId, 'name' => 'Home', 'type' => 'url', 'url' => '/']);
Navigate::getInstance()->nodes->save($home); // assigns the order, $home->id is set afterwards

Navigate::getInstance()->nodes->save(new NodeRecord([
    'navId' => $navId, 'siteId' => $siteId, 'name' => 'About us', 'parent' => $home->id,
    'type' => 'element', 'elementType' => 'entry', 'elementId' => 42, // 'entry' | 'asset'
]));
```

| `type`    | Extra attributes           | Notes                                              |
| --------- | -------------------------- | -------------------------------------------------- |
| `url`     | `url`                      | Literal/relative URL; supports env vars & `{config}` tokens |
| `element` | `elementType`, `elementId` | `elementType` is `entry` or `asset`; the URL comes from the element |
| `heading` | —                          | Non-link label; only meaningful when `levels > 1`  |

Optional node attributes: `blank` (open in new tab), `classes`, `enabled` (default `true`) and `parent` (a parent node ID).

## Configuration

You can create a file called ``config/craft/navigate.php`` (run ``php craft craft:setup:publish`` or copy [this one](config/navigate.php) to start) to manage these settings in your code. The ones listed here are not available through the CP.

### Disabling caching
Out of the box, the plugin will cache the querries it makes when ``devMode`` is not enabled. 

If you want to disable caching within the plugin entirely, you can do so by setting ``disableCaching`` to ``true`` in the plugin's configuration file. (see example [here](https://github.com/studioespresso/craft3-navigate/blob/master/src/config.php))

###  CSS class option list

Instead of the "Classes" field being a plain text field, you can also change it to a predefined dropdown with class that you want to make available for the user.
The ``nodeClasses`` setting takes an array where that contains "Class to be applied" => "Label to be shown in the cp".


````php
"nodeClasses" => [
    '' => '---',
    'nav nav-primary' => "Primary",
    'nav nav-highlight' => 'Highlight'
]
````

Make sure to also include an empty option in case you don't want the the first item to be selected by default

---
Brought to you by [Studio Espresso](https://www.studioespresso.co/)
