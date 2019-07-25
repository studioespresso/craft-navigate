# Release notes for Navigate for Craft CMS 3.x

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

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
