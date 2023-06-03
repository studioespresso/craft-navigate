# Release notes for Navigate for Craft CMS 3.x

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 3.1.2 - 2023-06-03
### Fixed
- Fixed an issue with multiple site groups ([#65](https://github.com/studioespresso/craft3-navigate/issues/65))

## 3.1.1 - 2023-05-31
### Fixed
- Fixed reposition nodes by grabbing the "move" icon 

## 3.1.0 - 2023-05-14
### Added
- Editing navigation nodes now uses Craft's SlideOut panel instead of a popup
- Navigations overview is now based on Craft's VueAdminTable instead of twig


## 3.0.2 - 2022-10-10
### Added
- Added settings to allow new navigations to be created in read-only mode [#66](https://github.com/studioespresso/craft3-navigate/issues/66)


## 3.0.1 - 2022-05-18
### Fixed
- Fixed an error on the navigations overview screen ([#64](https://github.com/studioespresso/craft3-navigate/issues/64))

## 3.0.0 - 2022-05-01
### Added
- Craft 4 🚀

## 2.9.0 - 2022-03-13
### Added
- Added "current" property to check wether or not a node's url is an exact match to the current url ([#61](https://github.com/studioespresso/craft3-navigate/pull/61), thanks [@stevecomrie](https://github.com/stevecomrie))
- Better checks for ``allowAdminChanges`` to prevent config changes in environments where project config is read only. ([#59](https://github.com/studioespresso/craft3-navigate/issues/59))

## 3.0.0-beta.1 - 2022-03-02
### Added
- Craft CMS 4 compatibility

## 2.8.1 - 2021-09-28
### Fixed
- Fixed an issue where the sites for which a nav was enabled wasn't checked against the sites a user could edit
- Fixed an issue where active state checking would only on PHP 8 or higher

## 2.8.0 - 2021-07-18
### Added
- The Node classes field now be filled with predefined values, more information can be found in the [readme](https://github.com/studioespresso/craft3-navigate/tree/master#css-class-option-list) ([#57](https://github.com/studioespresso/craft3-navigate/issues/57)).
- Navigation items are now automatically disabled when the related element is deleted, and enabled when the element should be restored ([#39](https://github.com/studioespresso/craft3-navigate/issues/39)).

## 2.7.8 - 2021-06-28
### Fixed
- Fixed amNav upgrade migration

## 2.7.7 - 2021-06-08
### Fixed
- Fixed an error that occured when changing the name of a heading
- Fixed some issues in the amNav upgrade migration


## 2.7.6 - 2021-04-18
### Fixed
- Fixed an issue with active states when the url included query parameters ([#54](https://github.com/studioespresso/craft3-navigate/issues/54))

## 2.7.5 - 2021-03-30
### Fixed
- Fixed an issue with dragging node with children to the top of a navigation tree ([#56](https://github.com/studioespresso/craft3-navigate/issues/56))

## 2.7.4 - 2021-01-14
### Fixed
- Fixed an small issue with active states on similar urls

## 2.7.3 - 2020-11-02
### Fixed
- Fixed an error on navigation detail cause in 2.7.2

## 2.7.2 - 2020-10-26
### Fixed
- Plugin now installs with Composer 2.0 [#51](https://github.com/studioespresso/craft3-navigate/issues/51)

## 2.7.1 - 2020-09-17
### Fixed
- Fixed an issue where removing SuperTable elements would crash on our after delete event.

## 2.7.0 - 2020-08-29
### Added
- Added the option to disable caching on the plugin side entirely. ([#43](https://github.com/studioespresso/craft3-navigate/issues/43))

## 2.6.2 - 2020-06-25
### Fixed
- More persmission fixes for sites with 1 navigation 

## 2.6.1 - 2020-05-15
### Fixed
- Fixed a small issue with the editable sites redirect when only 1 site was enabled

## 2.6.0 - 2020-03-28
### Added
- ``navigate.getRaw`` now has the option to take a siteId as a second optional argument ([#47](https://github.com/studioespresso/craft3-navigate/issues/47))
- Added ``hasChildren`` and ``listClasses`` functions to make migration from amNav easier ([#46](https://github.com/studioespresso/craft3-navigate/issues/46))

## 2.5.2 - 2020-03-25
### Fixed
- Fixed an installation issue for Postgresql users ([#44](https://github.com/studioespresso/craft3-navigate/issues/44))
- Fixed a crash on installs that run Craft versions before 3.2 ([#45](https://github.com/studioespresso/craft3-navigate/pull/45))

## 2.5.1 - 2020-03-12
### Fixed
- Fixed an issue that causes navigations with multilple levels to crash in the CP ([#42](https://github.com/studioespresso/craft3-navigate/issues/42))


## 2.5.0 - 2020-03-04
### Added
- Added a 'Navigation' field that lists all navigations, so you can customize navigations per entry when needed ([#41](https://github.com/studioespresso/craft3-navigate/issues/41))


### Fixed
- Navigations are not cached when ``devMode`` is on.
- Urls with ``token`` params are not cached ([#38](https://github.com/studioespresso/craft3-navigate/issues/38))
- Nodes are linked based on their site id in the CP ([#40](https://github.com/studioespresso/craft3-navigate/issues/35))

## 2.4.4 - 2019-12-11
### Added
- The entries select modal now also show a sites menu you you can link entries from other sites ([#35](https://github.com/studioespresso/craft3-navigate/issues/35))

### Fixed
- Fixed the active state check for url type nodes ([#36](https://github.com/studioespresso/craft3-navigate/issues/36))

## 2.4.3 - 2019-09-10
### Fixed
- Fixed an issues with navigation persmissions for installs with only 1 site


## 2.4.2 - 2019-09-03
### Fixed
- Removed an unused migration that caused upgrade issues after 2.4.0

## 2.4.1 - 2019-09-02
### Fixed
- Checked a couple of issues with excisting navigations after upgrading to 2.4.0

## 2.4.0 - 2019-09-02
### Added
- Node urls are now displayed on the overview to better differentiate between entries with the same name ([#34](https://github.com/studioespresso/craft3-navigate/issues/34)). 
- Navigations now be enabled/disabled per sitegroup for a better multsite author experience

### Improved
- The sitegroup is now also listed in the sites dropdown

## 2.3.0 - 2019-07-25
### Added
- Menu headings can now be added, making it easier to work build larger multi-level or multi column menus ([#29](https://github.com/studioespresso/craft3-navigate/issues/29))
- Full upgrade support for sites that used amNav on Craft 2

### Fixed
- Disabled nodes on level 1 or deeper are visible in the CP again

## 2.2.1 - 2019-05-06
### Fixed
- Fixed an issue with linking to an asset

## 2.2.0 - 2019-04-15
### Added
- Added support for ``project-config/rebuild`` - plugin now requires Craft 3.1.20

## 2.1.3 - 2019-03-21
### Fixed
- Last of the caching update/fixes? Hopefully 🙂 - Fixed an issue where navigation cache wasn't being cleared correctly


## 2.1.2 - 2019-03-20
### Fixed
- Fixed a regression error where we stopped clearing Blitz cache in 2.1.1

## 2.1.1 - 2019-03-20
### Fixed
- Fixed an issue with caching where, after moving them, nodes wouldn't show up on the frontend anymore

## 2.1.0 - 2019-03-18
### Added
- [Blitz ⚡️](https://plugins.craftcms.com/blitz) support! Updating your navigations will now also clear & warm Blitz's cache if it's installed (requires Blitz 2.0.1)
- Navigation caches can now be cleared from the CP, under utilities/clear-caches.

## 2.0.2 - 2019-02-20
### Fixed
- Fixed an issue where a disabled element would still be included in the frontend query ([#21](https://github.com/studioespresso/craft3-navigate/issues/21))
- Fixed an issue where editing a navigaation would always link to the default site navigation ([#23](https://github.com/studioespresso/craft3-navigate/issues/23))
- Show only editable sites in the edit screen dropdown ([#23](https://github.com/studioespresso/craft3-navigate/issues/23))

### Improved
- The site selection dropdown is no longer shown in the entries/assets/categories modal ([#23](https://github.com/studioespresso/craft3-navigate/issues/23))
- URL nodes are now also parsed for variables from your .env file

## 2.0.1 - 2019-01-30
### Fixed
- Fixed an issue where disabled child nodes would be included in site querries

## 2.0.0 - 2019-01-16
### Added
- Craft 3.1 is here! Navigate is fully compatible and now support Craft's "project config", allowing you to include navigations in your project.yaml 

## 2.0.0-beta.1 - 2018-12-27
### Added
- Project config support! Adding, changing settings and removing navigations is now done through Craft's project config. 

## 1.4.0 - 2018-12-13
### Improved
- More performance tweaks: navigations are only queried once if they appear on a page multiple times.
- Better logging when moving or editing nodes in the CP goes wrong, or when building the cache goes wrong

### Fixed
- The category node label now fits on one line [#15](https://github.com/studioespresso/craft3-navigate/issues/15) 

## 1.3.2 - 2018-11-19
### Fixed
- Fixed an issue where IE11 users could not move nodes

### Added
- URL nodes are now also parsed for variables from your ``config/general.php``

## 1.3.1 - 2018-11-19
### Fixed
- Fixed an issue with rebuilding navigation caches after making changes in the CP

## 1.3.0 - 2018-11-19
### Added
- URL nodes can now contain Craft aliases like `@web` or your own custom aliases. [#18](https://github.com/studioespresso/craft3-navigate/issues/18)

### Improved 
- Greatly improved querries for pages with multiple navigations filled with elements

## 1.2.4 - 2018-09-27
### Fixed
- Fixed an issue where removing a node from a navigation would not refresh the cache

## 1.2.3 - 2018-09-27
### Fixed
- Added missing use statement

## 1.2.2 - 2018-09-27
### Fixed
- Fixed an issue with caching nodes for diferent sites.

## 1.2.1 - 2018-09-12
### Fixed
- Fixed a crashing issue with fresh installs where defaultNodeType would still be in the database

## 1.2.0 - 2018-09-10
### Added
- Added some big performance improvments, especially for sites using lots of different navigations per page. Navigations are now cached and updated on change.
### Fixed
- Fixed an issue with active states with the same word would occur twice in a url([#14](https://github.com/studioespresso/craft3-navigate/issues/14))

## 1.1.3 - 2018-09-03
### Fixed
- Fixed an error with getting the edit url for an category that doesn't excist anymore ([#13](https://github.com/studioespresso/craft3-navigate/issues/13))

## 1.1.2 - 2018-09-02
### Fixed
- Fixed an error with getting the edit url for an entry that doesn't excist anymore ([#13](https://github.com/studioespresso/craft3-navigate/issues/13))


## 1.1.1 - 2018-07-25
### Fixed
- Fixed an error when a navigation contained an element that doesn't excist anymore ([#11](https://github.com/studioespresso/craft3-navigate/issues/11))

## 1.1.0 - 2018-07-21

### Added
- Node are now active when on a child url that is not in the navigation ([#10](https://github.com/studioespresso/craft3-navigate/issues/10))
- Added Dutch (NL) translations ([#5](https://github.com/studioespresso/craft3-navigate/issues/5))
- Node title now links to the entry's edit page or show the edit modal for url nodes ([#2](https://github.com/studioespresso/craft3-navigate/issues/2))

### Removed
- The "Default Node Type" setting wasn't used for anything so this update removes it 

## 1.0.1 - 2018-07-09

### Changed
- Opening the URL modal now moves focus to the name field, so you can start typing right away ([#7](https://github.com/studioespresso/craft3-navigate/issues/7))
### Fixed
- Sites in the multisite dropdown now stay in the same order ([#6](https://github.com/studioespresso/craft3-navigate/issues/6))
- Styling issue with node type in Chrome and Firefox ([#8](https://github.com/studioespresso/craft3-navigate/issues/8))

## 1.0.0 - 2018-07-05
### Added
- Initial release
