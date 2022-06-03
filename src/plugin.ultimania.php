<?php
/*
 * Plugin: ultimania
 * ~~~~~~~~~~~~~~~~~
 * This plugin is made to replace the database Gerymania. Gerymania was sadly
 * the only global record database supporting the Stunts mode. This wouldn't be
 * the problem, if it would be fast and have just that basic features like
 * a ban system for cheaters (in stunt cheating is very easy :/).
 * This is the reason, why this plugin have been coded.
 * 
 * ----------------------------------------------------------------------------------
 * Author:		askuri
 * Version:		See "const ULTI_PLUGIN_VERSION"
 * Date:		03.06.2022
 * Copyright:	2022 Martin Weber
 * Game:		Trackmania Forever (TMF) only
 * ----------------------------------------------------------------------------------
 *
 * LICENSE: This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 *
 * ----------------------------------------------------------------------------------
 *
 * Dependencies: none
 * 
 * Manialink-IDs:
 * - ultimania_pbwidget
 * - ultimania_window
 * 
 * 
 * Action-IDs:
 * - 5450101 = Open records window
 * - 5450102 = Close records window
 * - 5450104 = Open detailed record list
 * - 54502xx = Recordinfo
 */

Aseco::registerEvent('onNewChallenge2',				'ulti_onNewChallenge2'); /** @phpstan-ignore-line */
Aseco::registerEvent('onPlayerFinish',				'ulti_onPlayerFinish'); /** @phpstan-ignore-line */
Aseco::registerEvent('onPlayerConnect',				'ulti_onPlayerConnect'); /** @phpstan-ignore-line */
Aseco::registerEvent('onEndRace1',					'ulti_onEndRace1'); /** @phpstan-ignore-line */
Aseco::registerEvent('onEverySecond',				'ulti_onEverySecond'); /** @phpstan-ignore-line */
Aseco::registerEvent('onPlayerManialinkPageAnswer', 'ulti_onMlAnswer'); /** @phpstan-ignore-line */
Aseco::registerEvent('onMenuLoaded', 				'ulti_onMenuLoaded'); /** @phpstan-ignore-line */
Aseco::registerEvent('onUltimaniaRecordsLoadedApi2', 'ulti_onUltimaniaRecordsLoadedApi2'); /** @phpstan-ignore-line */
Aseco::registerEvent('onUltimaniaRecordApi2',       'ulti_onUltimaniaRecordApi2'); /** @phpstan-ignore-line */

Aseco::addChatCommand('ultirankinfo', 'Shows informations about a record', false); /** @phpstan-ignore-line */
Aseco::addChatCommand('ultiwindow', 'Shows informations about the current map', false); /** @phpstan-ignore-line */
Aseco::addChatCommand('ultiupdate', 'Updates Ultimania', false); /** @phpstan-ignore-line */
Aseco::addChatCommand('ultilist', 'Shows all records', false); /** @phpstan-ignore-line */

const ULTI_PLUGIN_VERSION = '2.0.1';
const ULTI_API_VERSION    = '5'; // DO NOT CHANGE!!! You may get strange or outdated results so keep it as is
const ULTI_ID_PREFIX      = 5450; // Change this if unexpected things happen when clicking something
const ULTI_MIN_PHP        = '5.6.0'; // Minimum required PHP version
const ULTI_MIN_XASECO     = '1.14'; // Minimum required XAseco version

const ULTI_EVENT_RECORDS_LOADED_LEGACY = 'onUltimaniaRecordsLoaded';
const ULTI_EVENT_RECORDS_LOADED_API2   = 'onUltimaniaRecordsLoadedApi2';
const ULTI_EVENT_RECORD_LEGACY         = 'onUltimaniaRecord';
const ULTI_EVENT_RECORD_API2           = 'onUltimaniaRecordApi2';

global $ulti;
global $ultiMainClass;
global $ultiXasecoAdapter;

require 'Ultimania.php';
require 'UltimaniaConfig.php';
require 'UltimaniaDtoMapper.php';
require 'UltimaniaClient.php';
require 'UltimaniaRecord.php';
require 'UltimaniaRecords.php';
require 'UltimaniaRecordImprovement.php';
require 'UltimaniaPlayer.php';
require 'UltimaniaLegacyAdapter.php';
require 'UltimaniaXasecoAdapter.php';

/*
 * @ plugin authors: don't access these objects directly.
 * Use the events onUltimaniaRecordsLoadedApi2 and onUltimaniaRecordApi2 instead.
 * I don't guarantee not to break the interface.
 */

$ultiConfig = UltimaniaConfig::instantiateFromFile('ultimania.xml');

$ultiDtoMapper = new UltimaniaDtoMapper();
$ultiClient = new UltimaniaClient($ultiConfig, $ultiDtoMapper);
$ultiRecords = new UltimaniaRecords($ultiClient);
$ultiXasecoAdapter = new UltimaniaXasecoAdapter();
$ultiMainClass = new Ultimania($ultiConfig, $ultiRecords, $ultiXasecoAdapter, $ultiClient);

// deprecated! Use onUltimaniaRecordsLoadedApi2 and onUltimaniaRecordApi2 instead
$ulti = new UltimaniaLegacyAdapter($ultiConfig, $ultiRecords);

/**
 * Chatcommands
 */

function chat_ultirankinfo($aseco, $command) { /** @phpstan-ignore-line */
    global $ultiMainClass;
    $ultiMainClass->onChatUltiRankInfo($command['author'], $command['params']);
}

function chat_ultiwindow($aseco, $command) { /** @phpstan-ignore-line */
    global $ultiMainClass;
    $ultiMainClass->onChatUltiWindow($command['author']);
}

function chat_ultiupdate($aseco, $command) { /** @phpstan-ignore-line */
    global $ultiMainClass;
    $ultiMainClass->onChatUltiUpdate($command['author']);
}

function chat_ultilist($aseco, $command) { /** @phpstan-ignore-line */
    global $ultiMainClass;
    $ultiMainClass->onChatUltiList($command['author']);
}

/**
 * Callbacks
 */
function ulti_onSync($aseco) { /** @phpstan-ignore-line */
    global $ultiMainClass;
    $ultiMainClass->onStartup();
}
function ulti_onNewChallenge2($aseco, $challenge_item) { /** @phpstan-ignore-line */
    global $ultiMainClass;
    $ultiMainClass->onNewChallenge($challenge_item);
}
function ulti_onPlayerFinish($aseco, $finish) { /** @phpstan-ignore-line */
    global $ultiMainClass;
    $ultiMainClass->onPlayerFinish($finish);
}
function ulti_onPlayerConnect($aseco, $player) { /** @phpstan-ignore-line */
    global $ultiMainClass, $ultiXasecoAdapter;

    if (! $ultiXasecoAdapter->isXasecoStartingUp()) {
        $ultiMainClass->onPlayerConnect($player);
    }
}
function ulti_onEndRace1($aseco, $race) { /** @phpstan-ignore-line */
    global $ultiMainClass;
    $ultiMainClass->onEndRace();
}
function ulti_onEverySecond($aseco) { /** @phpstan-ignore-line */
    global $ultiMainClass;
    $ultiMainClass->onEverySecond();
}
function ulti_onMlAnswer($aseco, $answer) { /** @phpstan-ignore-line */
    global $ultiMainClass, $ultiXasecoAdapter;
    $ultiMainClass->onMlAnswer($answer[0], $ultiXasecoAdapter->getPlayerObjectFromLogin($answer[1]), $answer[2]);
}
function ulti_onMenuLoaded($aseco, $menu) { /** @phpstan-ignore-line */
    // Doc: http://fish.stabb.de/index.php5?page=downloads&subpage=148
    $menu->addEntry('tracks', 'fulllist', true, 'Ultimania', 'ultifolder', '');

    $menu->addEntry('', 'tracks', false, 'Ultimania', 'ulti');
    $menu->addEntry('ulti', '', true, 'Full recordlist', 'ulti_fulllist', '/ultilist');
    $menu->addEntry('ulti', '', true, 'Open window', 'ulti_openwindow', '/ultiwindow');
    $menu->addEntry('ulti', '', true, '$ff0Update plugin', 'ulti_openwindow', '/ultiupdate', 'MasterAdmin');
}
function ulti_onUltimaniaRecordsLoadedApi2($aseco, $records) { /** @phpstan-ignore-line */
    global $ulti;
    $ulti->mapRecordsLoadedEventToLegacyEvent($records);
}
function ulti_onUltimaniaRecordApi2($aseco, $record) { /** @phpstan-ignore-line */
    global $ulti;
    $ulti->mapRecordEventToLegacyEvent($record);
}
