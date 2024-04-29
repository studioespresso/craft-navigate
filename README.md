# Navigate for Craft CMS 3.x

## Requirements

This plugin requires Craft CMS 4.9.0 or later.

A version compatible with Craft 3.0.0 can be found [here](https://github.com/studioespresso/craft3-navigate/tree/Craft_3.0.x)

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
    {% for node in nodes %}
        {{ macros.renderNode(node) }}
    {% endfor %}
    </ul>
</div>
```

### craft.navigate.render

The `render` function gives you the option to let the plugin build the HTML for you. You can add the following options:

```twig
{{ craft.navigate.render('navHandle', {
    wrapperClass : 'navbar',
    ulClass: 'navbar-nav',
    listClass: 'nav-item',
    linkClass: 'nav-link'
}) }}
```

This will return HTML, with the classes you specified, based on how [Bootstrap](http://getbootstrap.com/docs/4.1/components/navbar/) does navigations

## Headings

Since 2.3.0, headings can be added to make it easier to build larger navigations with mulitple sections or columns. To make full use of this you check check ``node.type == "heading"`` and add the html you need in the condition. 

These only work on multiple levels (eg: 3 headings on the top level, each with navigation items on deeper levels). 

## Configuration

You can create a file called ``navigate.php`` in the ``config`` directory (you can copy [this one](https://github.com/studioespresso/craft3-navigate/blob/master/src/config.php) to start) to manage these settings in your code. The ones listed here are not available through the CP.

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
Brought to you by [Studio Espresso](https://www.studioespresso.dev/)
