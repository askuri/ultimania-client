# Ultimania Xaseco Plugin

## Development

### Setup

- `git clone git@github.com:askuri/ultimania-client.git`
- `composer install`
- Get a clean xaseco installation and copy it into `xaseco` folder. This helps your IDE with auto-completion.

### Running this with xaseco directly

If you want to test this in xaseco directly, do the following:
- Create a symbolic link of [src/plugin.ultimania.php](src/plugin.ultimania.php) into your xaseco plugins folder.
- Create a symbolic link of [src/ultimania.xml](src/ultimania .xml) into your xaseco folder
- In your plugins.xml, include `<plugin>plugin.ultimania.php</plugin>`

### Unit tests

Run `composer run-script phpunit`

### Static analysis

This project uses PHPStan. Run it with `composer run-script phpstan`

## Building / Packaging

- Set date and version in plugin.ultimania.php
- Copy the content of the includes in plugin.ultimania.php directly into the file, so only one PHP file needs to be released
- Release plugin.ultimania.php and ultimania.xml

## Changelog

### 2.0.0 (14.05.2022)

- Cleaned up the code base a good bit
- Show messages about record improvement on player finish
- Save replays and allow players to view them
- Fix inconsistent alignment and coloring in PB Widget
- Fix broken nicknames / character encodings
- Several minor improvements

### 1.4.1 (10.8.2016)

- Hotfix (crash on /ultilist)
- Hotfix (PB not loaded properly sometimes)
- Clicking PB widget now opens detailed windows instead of regular one

### 1.4.0 (10.8.2016)

- Fixed bug: double records when record improves
- Show global personal best at bottom right

### 1.3.2 (27.8.2015)

- Again a bugfix, sorry. last bug in the list of the last update (score refresh) havent't been fixed 100% :/

### 1.3.1 (25.8.2015)

- Current API URL is now fetched on every map start. Makes it easier to shut down the server for maintainance
- Fixed warning about invalid foreach() arguments if no records are available
- Fixed false message "Unable to get API URL from http://askuri.de/ultimania/url.txt"
- Event "onUltimaniaRecordsLoaded" also gets released when the records are grabbed from the server (each 180s)
- Fixed bug: scores weren't updated immediately if it was the first record

### 1.3.0 (27.2.2015)

- Fixed annoying actionid collison bug regarding Records Eyepiece Trackinfo widget and /ultirankinfo
- Autoclosing Eyepiece and standard windows now, when openening Ultimania window
- Fixed bug window wasnt displayed due to " ' & < > chars in nicknames
- Geryimported records are now tagged as such in /ultirankinfo and /ultilist
- Added FufiMenu integration
- fixed another small bug with the autoupdater (damn have it worked in any version i've released properly? :D)
- Instant refresh/merge of record list when new record was driven. No often refresh needed anymore
- Hardcoded refresh interval to 180 seconds (and track load). See above
- Removed <refresh_interval> from Config

### 1.2.0 (17.9.2014)

- Fixed Warning when player joins
- Fixed Warning when no records available and using /ultilist
- Window Infotext refreshs now on each opening
- Records displaying limited to top25 but still each record get saved
- Window reworked: added info button and login for each record
- Removed "Report Record" button, since I never really monitored that
- API Update (Version 4): Some tweaks for better record limit handling
- Fixed 2 Bugs with error handling (getNewestVersion() and getWelcomeWindowInfo())
- Fixed autoupdater Bug. It's sadly not possible to use 1.1.1's autoupdater. Only forgot 2 bracket :/

### 1.1.1 (30.1.2014)

- Bugfix Update (optional / not needed)
- Fixed minor bug with record displaying

### 1.1.0 (28.1.2014)

- Feature Update
- Added command /ultilist to view more than 50 Records. Widget is still forced to 50
- Fixed Autoupdater Bug. Xaseco restart was required before using autoupdater

### 1.0.0 (11.1.2014)

- Recode update: Plugin have been completly reworked, it's much more structured now
- Fixed /ultirankinfo bug: Error messages about wrong usage are now shown
- Minor UI improvements in Window
- Removed Onboard widget
- Records are now stored in $ulti->records instaed of $ultimania_db
- Added chatcommand /ultiwindow to show window when no widget is shown
- /ultirankinfo got some more eyecandy and the score is now also shown
- Fixed error messages bug on startup
- Added Autoupdater. Usage: /ultiupdate

### 0.2.1 Beta (07.12.2013)

- Support for Undef's "Third-party Plugins UpToDate"

### 0.2.0 Beta (07.12.2013)

- Eyepiece update: Records-Eyepiece supports now Ultimania
- Changed API URL
- Widget can be disabled in config

### 0.1.3 Beta (17.11.2013)

- Minor changes

### 0.1.1 Beta (01.05.2013)

- Updated API URL (normally not needed)
- Set Refresh from 3 to 5 seconds

### 0.1.0 Beta

- First public release (as requested from TM-Fire clan)
