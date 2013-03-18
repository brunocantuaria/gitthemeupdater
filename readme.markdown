# Git Theme Updater

With this plugin you will be able to update your themes directly from GitHub. Also works in MultiSite.

Based on plugin Theme Updater:

* https://github.com/UCF/Theme-Updater

Main additions:

* Interface to manually add repository URL
* Add support to private projects
* Fixed an issue with downgrade when passing through an authentication page.

## Installation

### 1 - Install Git Theme Updater on your WordPress site

If you're running a MultiSite you must activate it on the entire network.

### 2 - Publish your theme to a public GitHub Repository

### 3 - Add a version tag to your style.css

Example header:

    Theme Name: Example  
    Theme URI: http://example.com/  
    Github Theme URI: https://github.com/username/repo
    Description: My Example Theme
    Author: person
    Version: 1.0

### 4 - Create a new tag and push the change back to the repo

    $ git tag 1.0
    $ git push origin 1.0

### 5 - Insert it's repository URL on Git Theme Updater's page settings.

If it's a private project on GitHub, create an application and insert it's Secret and ID on plugin's page settings.

### 6 - Done!

Whenever you upload a new version to GitHub and push it's tag version, Git Theme Updater will detect and show an alert to update on your WordPress panel.

## Changelog

*** v1.0 - March 17, 2013
* Initial version released
