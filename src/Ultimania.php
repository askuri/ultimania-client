<?php

class Ultimania {
    /** @var UltimaniaRecords */
    private $records;

    /** @var UltimaniaConfig */
    private $config;

    /** @var UltimaniaXasecoAdapter */
    private $xasecoAdapter;

    /** @var int timestamp of when the next refresh should happen */
    private $nextRefresh;

    /** @var string the actual API URL. URL is retrieved from ULTI_API_INFO URL */
    private $apiUrl;

    public function __construct($ultiConfig, $ultiRecords, $xasecoAdapter) {
        $this->config = $ultiConfig;
        $this->records = $ultiRecords;
        $this->xasecoAdapter = $xasecoAdapter;
        $this->nextRefresh = time(); // do it immediately, when plugin initiated
    }

    /**************************
     * BEGIN EVENT LISTENERS
     **/
    public function onStartup() {
        $this->doEnvironmentChecks();
        $this->registerWithThirdpartyPluginsUpToDate();
    }

    public function onNewChallenge() {
        // regularly check if there's a new API URL
        $this->fetchAndSetApiUrl();

        $this->refreshRecordsListAndReleaseEvent();
        $this->showPbWidgetToEveryone();
    }

    public function onPlayerFinish(Record $finish_item) {
        if ($finish_item->score == 0) return; // reject scores with 0 points

        $improvement = $this->records->insertOrUpdate($this->mapXasecoRecordToUltiRecord($finish_item));

        $response = $this->submitRecordToApi($improvement->getNewRecord());

        // We got the response from the server. Let's look if the player is banned
        if ($response['banned'] == true) {
            // Banned, so we punish him with this spam messages every finish
            $message = ('$f00Your Records won\'t be saved on Ultimania because you\'re banned (Reason: ' . $response['reason'] . ')');
            $this->xasecoAdapter->chatSendServerMessageToPlayer($message, $finish_item->player);
            return;
        }

        $this->displayPlayerFinishChatMessage($finish_item->player, $improvement);

        // Not banned, so we throw event
        $this->releaseUltimaniaRecordEvent($improvement->getNewRecord());
    }

    public function onPlayerConnect(Player $player) {
        $banned_players = $this->fetchBannedPlayers();

        foreach ($banned_players as $data) {
            if ($data['login'] == $player->login) {
                // display information window for the banned player
                $header = 'Ultimania global record database information:';
                $data = array();
                $data[] = array('$f00You\'re banned from the global records database Ultimania!');
                $data[] = array('');
                $data[] = array('This means:');
                $data[] = array('- You can\'t drive records anymore');
                $data[] = array('- You will always see this window on server join');
                $data[] = array('');
                $data[] = array('You can be unbanned by writing an apology for whatever you\'ve done');
                $data[] = array('(cheating, hacking, ...) to enwi2@t-online.de (the e-mail of the owner of Ultimania, Askuri).');
                $data[] = array('If Askuri thinks you regret whatever you did, he\'ll unban you.');
                $data[] = array('');
                $data[] = array('Best regards');
                display_manialink($player->login, $header, array('Icons64x64_1', 'TrackInfo', -0.01), $data, array(1.1), 'OK');
            }
        }

        // Remove Dedimania PB widget in bottom-right corner
        if (function_exists('chat_recpanel')) {
            $command = [];
            $command['author'] = $player;
            $command['params'] = 'RightBottomNoDedi';
            chat_recpanel($this->xasecoAdapter->getAsecoObject(), $command);
        }

        // display global PB widget
        $recordsByLogin = $this->records->getRecordsIndexedByLogin();
        $this->pbWidgetShow($player, $recordsByLogin[$player->login]);
    }

    public function onEndRace1() {
        $this->mainWindowHide(false); // Close window to all players
        $this->pbWidgetHide();
    }

    public function onEverySecond() {
        if (time() >= $this->nextRefresh) {
            $this->refreshRecordsListAndReleaseEvent();

            $this->nextRefresh = time() + $this->config->getRefreshInterval();
        }
    }

    public function onMlAnswer($answer) {
        $player = $this->xasecoAdapter->getPlayerObjectFromLogin($answer[1]);
        $actionId = $answer[2];

        switch ($actionId) {
            case ULTI_ID_PREFIX . 101:
                // Close Records Eyepiece windows
                $this->xasecoAdapter->releaseOnPlayerManialinkPageAnswerEvent($answer[0], $player, 91800);

                // Close standard windows like /list
                $this->xasecoAdapter->releaseOnPlayerManialinkPageAnswerEvent($answer[0], $player, 0);

                $this->mainWindowShow($player);
                break;
            case ULTI_ID_PREFIX . 102:
                $this->mainWindowHide($player);
                break;
            case ULTI_ID_PREFIX . 104:
                $this->showFullRecordList($player);
                break;
            default: // special cases

                // Prefix for recordinfos is 2
                if (substr($actionId, 0, strlen(ULTI_ID_PREFIX) + 1) == ULTI_ID_PREFIX . 2) {
                    $rank = substr($actionId, strlen(ULTI_ID_PREFIX) + 1) + 1;

                    $this->showUltiRankInfo($player, $rank);
                    $this->mainWindowHide($player);
                }
        }
    }

    public function onChatUltiList($command) {
        $this->showFullRecordList($command['author']);
    }

    public function onChatUltiRankInfo($command) {
        $player = $command['author'];

        if (!is_numeric($command['params'])) {
            $this->xasecoAdapter->chatSendServerMessageToPlayer('$ff0> $f00Usage: $bbb/ultirankinfo ranknumber$f00  (e.g. $bbb/ultirankinfo 1$f00)', $player);
            return;
        }

        $rank = $command['params'] - 1;

        $this->showUltiRankInfo($player, $rank);
    }

    public function onChatUltiWindow($command) {
        $this->mainWindowShow($command['author']);
    }

    public function onChatUltiUpdate($command) {
        /** @var Player $player */
        $player = $command['author'];
        if ($this->xasecoAdapter->isMasterAdmin($player)) {
            $newest = $this->fetchNewestAvailableUltimaniaClientVersion();
            if (version_compare(ULTI_VERSION, $newest) == -1) {
                $content = file_get_contents(ULTI_API_INFO . 'tmf/versions/' . $newest . '.php_');

                if ($content) {
                    if (!file_put_contents(getcwd() . '/plugins/plugin.ultimania.php', $content)) {
                        $this->xasecoAdapter->chatSendServerMessageToPlayer('$f00Unable to replace "plugin.ultimania.php". Please check the file-rights and try again.', $player);
                    } else {
                        $this->xasecoAdapter->chatSendServerMessageToPlayer('$0f0Successfully updated. Please restart XAseco', $player);
                    }
                } else {
                    $this->xasecoAdapter->chatSendServerMessageToPlayer('$f00Getting newest version file failed!', $player);
                }
            } else {
                $this->xasecoAdapter->chatSendServerMessageToPlayer('$0f0No update available', $player);
            }
        } else {
            $this->xasecoAdapter->chatSendServerMessageToPlayer('$f00No permissions!', $player);
        }
    }

    /**
     * END EVENT LISTENERS
     **************************/

    /*************************************
     * BEGIN METHODS FOR API COMMUNICATION
     */

    private function sendRequest($action, $params = []) {
        // why is this here? why did i do this many years ago?
        if (!$this->xasecoAdapter->getCurrentChallengeObject()->uid) {
            trigger_error('[Ultimania] Error: trying to send request to server without a valid track UID. Action: '. $action, E_USER_ERROR);
        }

        $params_predefined = [ // These are send on all requests
            'action' => $action,
            'uid' => $this->xasecoAdapter->getCurrentChallengeObject()->uid,
            'mapname' => $this->xasecoAdapter->getCurrentChallengeObject()->name,
            'stunt' => 1,
            'server' => $this->xasecoAdapter->getServerObject()->serverlogin,
            'servername' => $this->xasecoAdapter->getServerObject()->nickname
        ];

        $merged_params = array_merge($params, $params_predefined);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_USERAGENT, 'TMF\Xaseco' . XASECO_VERSION . '\Ultimania' . ULTI_VERSION . '\API' . ULTI_API_VERSION);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $merged_params);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config->getConnectTimeout());
        curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, $this->config->getRequestTimeout());

        $responseJson = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($responseJson, true);

        if ($response === null && !empty($responseJson)) {
            trigger_error('[Ultimania] Could not json_decode API response! Please report this to enwi2@t-online.de! Raw result:', E_USER_WARNING);
            $this->xasecoAdapter->console($responseJson);
        }

        if (!empty($response['error'])) {
            trigger_error('[Ultimania] Error occurred on action ' . $params['action'] . ': ' . $response['error'], E_USER_WARNING);
        }

        return $response;
    }

    private function fetchRecordsFromServer() {
        $records = $this->sendRequest('gettop', ['limit' => 0]);

        if (empty($records)) return [];

        return $this->mapApiRecordDtosToUltiRecords($records);
    }

    private function submitRecordToApi(UltimaniaRecord $ultiRecord) {
        return $this->sendRequest('playerfinish', $this->mapUltiRecordToApiRecordDto($ultiRecord));
    }

    private function fetchBannedPlayers() {
        return $this->sendRequest('getbannedplayers');
    }

    private function fetchNewestAvailableUltimaniaClientVersion() {
        $ver = trim(file_get_contents(ULTI_API_INFO . 'tmf/version.txt'));

        if (!$ver) {
            trigger_error('[Ultimania] Unable to get current version information form ' . ULTI_API_INFO, E_USER_WARNING);
            return false;
        } else {
            return $ver;
        }
    }

    private function fetchWelcomeWindowInfo() {
        $info = file_get_contents(ULTI_API_INFO . 'tmf/description_window.txt');

        if (!$info) {
            trigger_error('[Ultimania] Unable to get window infobox content from ' . ULTI_API_INFO, E_USER_WARNING);
            return false;
        }

        return $info;
    }

    private function fetchAndSetApiUrl() {
        $this->apiUrl = trim(file_get_contents(ULTI_API_INFO . 'url.txt')) . 'TMF/' . ULTI_API_VERSION . '/index.php';
        if (!$this->apiUrl) {
            trigger_error('[Ultimania] Unable to get API URL from ' . ULTI_API_INFO . 'url.txt', E_USER_WARNING);
        }
    }

    /**
     * END METHODS FOR API COMMUNICATION
     *************************************/

    /*************************************
     * BEGIN MANIALINKS
     */

    /**
     * Show main window to player
     */
    private function mainWindowShow(Player $player) {
        $ultinfo = $this->fetchWelcomeWindowInfo();

        $xml = '<manialink id="ultimania_window">
			
			<quad posn="0 3 0" sizen="79 54" bgcolor="001a" halign="center" valign="center" />
			<quad posn="0 3 2" sizen="81 56" style="Bgs1InRace" substyle="BgCard3" halign="center" valign="center" />
			
			<quad posn="0 29.5 1" sizen="82 5" style="BgsPlayerCard" substyle="BgRacePlayerLine" halign="center" />
			<quad posn="0 30 0.5" sizen="80 5" style="Bgs1InRace" substyle="BgTitle3_3" halign="center" />
			<label posn="0 29 2" style="TextRankingsBig" halign="center" text="Ultimania World Records" />
			
			<quad posn="33.5 30 3" sizen="5 5" style="Icons128x128_1" substyle="BackFocusable" action="5450102" />
			
			<label posn="-38 -23.65 3" text="$000$tUltimania TMF v' . ULTI_VERSION . ' using API ' . ULTI_API_VERSION . '" textsize="0" />
		';

        $xml .= '
			<quad posn="-38 1 1" sizen="35.5 47" style="BgsPlayerCard" substyle="BgRacePlayerName" valign="center" />
			<quad posn="-2 24.5 1" sizen="40 47" style="BgsPlayerCard" substyle="BgRacePlayerName" />
			
			<label posn="-1.5 24 2" text="' . $ultinfo . '" textsize="2" />
		';

        $xml .= '<frame posn="-38.5 26.5 1">';

        if ($this->records->isEmpty()) {
            $xml .= '<label posn="2 -5 1" text="$oCurrently' . CRLF . 'no records :(" textsize="2" />';
        } else {
            $x = 0;
            $y = -3;

            /**
             * @var $i int rank - 1
             * @var $record UltimaniaRecord
             */
            foreach ($this->records->getLimitedBy($this->config->getNumberOfRecordsDisplayLimit()) as $i => $record) {
                $rank = $i + 1;
                switch ($rank) {
                    case 1:
                        $xml .= '<quad posn="' . ($x + 1.5) . ' ' . $y . ' 1" sizen="1.5 1.5" style="Icons64x64_1" substyle="First" />';
                        break;
                    case 2:
                        $xml .= '<quad posn="' . ($x + 1.5) . ' ' . $y . ' 1" sizen="1.5 1.5" style="Icons64x64_1" substyle="Second" />';
                        break;
                    case 3:
                        $xml .= '<quad posn="' . ($x + 1.5) . ' ' . $y . ' 1" sizen="1.5 1.5" style="Icons64x64_1" substyle="Third" />';
                        break;

                    default:
                        $xml .= '<label posn="' . ($x + 1.2) . ' ' . $y . ' 1" text="' . $rank . '." textsize="1" />';
                }

                $xml .= '<label posn="' . ($x + 4) . ' ' . $y . ' 1" text="' . $record->getScore() . '" textsize="1" />';
                $xml .= '<label posn="' . ($x + 8) . ' ' . $y . ' 1" text="' . $this->handleSpecialChars($record->getNick()) . '" sizen="12 2" textsize="1" />';
                $xml .= '<label posn="' . ($x + 21) . ' ' . $y . ' 1" text="' . $record->getLogin() . '" sizen="13 2" textsize="1" />';

                if ($record->getLogin() == $player->login) {
                    $xml .= '<quad posn="' . ($x + 0.7) . ' ' . ($y + 0.2) . ' 0.5" sizen="35 2" style="BgsPlayerCard" substyle="ProgressBar" />';
                }

                $xml .= '<quad posn="' . ($x + 34.4) . ' ' . ($y - 0.1) . ' 2" sizen="1.35 1.35" style="Icons64x64_1" substyle="TrackInfo" action="' . ULTI_ID_PREFIX . 2 . $i . '" />';

                $y -= 1.83;
            }
        }

        $xml .= '</frame> </manialink>';

        $this->xasecoAdapter->sendManialinkToPlayer($player, $xml);
    }

    /**
     * @param $player Player|null
     */
    private function mainWindowHide($player) {
        $xml = '<manialink id="ultimania_window"></manialink>';

        if ($player === null) {
            $this->xasecoAdapter->sendManialinkToEveryone($xml);
        } else {
            $this->xasecoAdapter->sendManialinkToPlayer($player, $xml);
        }
    }

    /**
     * Record may be null, so we need the Player object to show a "no record yet" widget to the player
     * @param Player $player
     * @param UltimaniaRecord|null $record
     */
    private function pbWidgetShow($player, $record) {
        if ($record) {
            $pbText = $record;
        } else {
            $pbText = '---';
        }

        $xml = '<manialink id="ultimania_pbwidget">
			<frame posn="53.5 -32.7 0">
                <label posn="4.5 -6.2 2" sizen="10 2" halign="right" valign="center" textsize="1" textcolor="dddf" text="Ultimania PB:"/>
                <label posn="5.0 -6.2 2" sizen="6.5 2" halign="left" valign="center" textsize="1" textcolor="ffff" text="  ' . $pbText . '" action="' . ULTI_ID_PREFIX . '104"/>
			</frame>
		</manialink>';

        $this->xasecoAdapter->sendManialinkToPlayer($player, $xml);
    }

    private function pbWidgetHide() {
        $xml = '<manialink id="ultimania_pbwidget"></manialink>';
        $this->xasecoAdapter->sendManialinkToEveryone($xml);
    }

    private function showFullRecordList(Player $player) {
        if ($this->records->isEmpty()) {
            $this->xasecoAdapter->chatSendServerMessageToPlayer('$06fNo Records. Feel free to drive the first :)', $player);
            return;
        }

        $header = 'Ultimania records:'; // Window Header

        $player->msgs = [];

        // Settings
        $player->msgs[0] = [1, // startpage
            $header,
            array(1.3, 0.1, 0.15, 0.3, 0.35, 0.4), // widths: overall, col1, col2, col3, ...
            array('Icons64x64_1', 'TrackInfo') // icon style
        ];
        $page = 1;
        foreach ($this->records->getAll() as $id => $record) {
            $time = $this->timestampToDateTimeStringOrGery($record->getAddTime());
            $row = array($id + 1, $record->getScore(), $record->getNick(), $record->getLogin(), $time);

            $player->msgs[$page][] = $row;

            if ((($id + 1) % 15) == 0) $page++;
        }

        // display ManiaLink message
        display_manialink_multi($player);
    }

    /**
     * @param $showToPlayer Player player to show the information to
     * @param $rank int starting from 1. Rank that information should be shown about
     */
    private function showUltiRankInfo(Player $showToPlayer, $rank) {
        $record = $this->records->getRecordByRank($rank);
        if ($record === null) {
            $this->xasecoAdapter->chatSendServerMessageToPlayer('$ff0>> $f00This record does not exists', $showToPlayer);
            return;
        }

        $dateTimeStringOrGery = $this->timestampToDateTimeStringOrGery($record->getAddTime());

        $header = 'Ultimania record information:';

        $showToPlayer->msgs = array();
        $showToPlayer->msgs[0] = array(1, // startpage
            $header,
            array(0.8, 0.3, 0.5), // widths: overall, col1, col2, col3, ...
            array('Icons64x64_1', 'TrackInfo') // icon style
        );

        $showToPlayer->msgs[] = array(array('Score', '$FFC' . $record->getScore() . ' Points'),
            array('Login', '$FFC' . $record->getLogin()),
            array('Nickname', '$FFC' . $record->getNick()),
            array('Recorded on', '$FFC' . $dateTimeStringOrGery),
            array('$h[ulti:admin_rec?uid=' . $this->xasecoAdapter->getCurrentChallengeObject()->uid . '&login=' . $record->getLogin() . ']Admin')
        );

        // display ManiaLink message
        display_manialink_multi($showToPlayer);
    }

    /**
     * END MANIALINKS
     *************************************/

    /**********************
     * BEGIN HELPER METHODS
     */

    // stolen from records_eyepiece
    private function handleSpecialChars($string) {
        // Remove links, e.g. "$(L|H|P)[...]...$(L|H|P)"
        $string = preg_replace('/\${1}(L|H|P)\[.*?\](.*?)\$(L|H|P)/i', '$2', $string);
        $string = preg_replace('/\${1}(L|H|P)\[.*?\](.*?)/i', '$2', $string);
        $string = preg_replace('/\${1}(L|H|P)(.*?)/i', '$2', $string);

        // Remove formatting $S $H $W $I $L $O $N
        $string = preg_replace('/\${1}[SHWILON]/i', '', $string);

        // Convert & " ' > <

        $string = str_replace(array('&', '"', "'", '>', '<'),
            array('&amp;', '&quot;', '&apos;', '&gt;', '&lt;'),
            $string
        );
        $string = stripNewlines($string);    // stripNewlines() from basic.inc.php

        return validateUTF8String($string);    // validateUTF8String() from basic.inc.php
    }

    // Stolen from plugin.records_eyepiece.php / undef, who stole it from basic.inc.php and adjusted it
    private function formatTime($MwTime, $hsec = true) {
        if ($MwTime == -1) {
            return '???';
        } else {
            $hseconds = (($MwTime - (floor($MwTime / 1000) * 1000)) / 10);
            $MwTime = floor($MwTime / 1000);
            $hours = floor($MwTime / 3600);
            $MwTime = $MwTime - ($hours * 3600);
            $minutes = floor($MwTime / 60);
            $MwTime = $MwTime - ($minutes * 60);
            $seconds = floor($MwTime);
            if ($hsec) {
                if ($hours) {
                    return sprintf('%d:%02d:%02d.%02d', $hours, $minutes, $seconds, $hseconds);
                } else {
                    return sprintf('%d:%02d.%02d', $minutes, $seconds, $hseconds);
                }
            } else {
                if ($hours) {
                    return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
                } else {
                    return sprintf('%d:%02d', $minutes, $seconds);
                }
            }
        }
    }

    private function doEnvironmentChecks() {
        if (version_compare(phpversion(), ULTI_MIN_PHP, '<')) {
            trigger_error('[Ultimania] Not supported PHP version (' . phpversion() . ')! Please update to min. version ' . ULTI_MIN_PHP . '!', E_USER_ERROR);
        }

        if (!function_exists('curl_init')) {
            trigger_error('[Ultimania] You need to enable cURL in php.ini!', E_USER_ERROR);
        }

        // Check for the right XAseco-Version
        if (defined('XASECO_VERSION')) {
            if (version_compare(XASECO_VERSION, ULTI_MIN_XASECO, '<')) {
                trigger_error('[Ultimania] Not supported XAseco version (' . XASECO_VERSION . ')! Please update to min. version ' . ULTI_MIN_XASECO . '!', E_USER_ERROR);
            }
        } else {
            trigger_error('[Ultimania] Can not identify the System, "XASECO_VERSION" is unset! This plugin runs only with XAseco/' . ULTI_MIN_XASECO . '+', E_USER_ERROR);
        }

        if ($this->xasecoAdapter->getGameType() != 'TMF') {
            trigger_error('[Ultimania] This plugin supports TMF only. Can not start with a "' . $this->xasecoAdapter->getGameType() . '" Dedicated-Server!', E_USER_ERROR);
        }

        if ($this->xasecoAdapter->getGameInfo()->mode != Gameinfo::STNT) {
            trigger_error('[Ultimania] This plugin supports stunts game mode only. Current game mode is: ' . $this->xasecoAdapter->getGameInfo()->getMode(), E_USER_ERROR);
        }
    }

    private function timestampToDateTimeStringOrGery($timestamp) {
        // Geryimports were done on 2013/24/03 and other dates. I keep searching them
        if ($timestamp > 1364148372 and $timestamp <= 1367798399) {
            $time = 'Gerymania Import';
        } else {
            $time = date('M j Y G:i:s', $timestamp) . '(CET)';
        }

        return $time;
    }

    // Register this to the global version pool (for up-to-date checks)
    private function registerWithThirdpartyPluginsUpToDate() {
        $this->xasecoAdapter->registerWithThirdpartyPluginsUpToDate(
            'plugin.ultimania.php',
            'askuri',
            ULTI_VERSION
        );
    }

    private function releaseRecordsLoadedEvent() {
        $this->xasecoAdapter->releaseEvent(ULTI_EVENT_RECORDS_LOADED_API2, $this->records->getAll());
    }

    private function releaseUltimaniaRecordEvent(UltimaniaRecord $record) {
        $this->xasecoAdapter->releaseEvent(ULTI_EVENT_RECORD_API2, $record);
    }

    private function refreshRecordsListAndReleaseEvent() {
        $this->records->setAll($this->fetchRecordsFromServer());
        $this->releaseRecordsLoadedEvent();
    }

    private function displayPlayerFinishChatMessage(Player $player, UltimaniaRecordImprovement $improvement) {
        // check if record should be shown according to settings
        if ($this->config->getDisplayRecordMessagesForBestOnly() &&
            $improvement->getNewRank() > $this->config->getNumberOfRecordsDisplayLimit()) {
            return;
        }

        switch ($improvement->getType()) {
            case UltimaniaRecordImprovement::TYPE_NEW:
                $message = $this->xasecoAdapter->formatColors(formatText($this->config->getMessageRecordNew(),
                    $player->nickname,
                    $improvement->getNewRank(),
                    $improvement->getNewRecord()->getScore(),
                    $improvement->getPreviousRank(),
                    $improvement->getRecordDifference()
                ));
                break;
            case UltimaniaRecordImprovement::TYPE_EQUAL:
                $message = $this->xasecoAdapter->formatColors(formatText($this->config->getMessageRecordEqual(),
                    $player->nickname,
                    $improvement->getNewRank(),
                    $improvement->getNewRecord()->getScore()
                ));
                break;
            case UltimaniaRecordImprovement::TYPE_NEW_RANK:
                $message = $this->xasecoAdapter->formatColors(formatText($this->config->getMessageRecordNewRank(),
                    $player->nickname,
                    $improvement->getNewRank(),
                    $improvement->getNewRecord()->getScore(),
                    $improvement->getPreviousRank(),
                    $improvement->getRecordDifference()
                ));
                break;
            case UltimaniaRecordImprovement::TYPE_FIRST:
                $message = $this->xasecoAdapter->formatColors(formatText($this->config->getMessageRecordFirst(),
                    $player->nickname,
                    $improvement->getNewRank(),
                    $improvement->getNewRecord()->getScore()
                ));
                break;
            default:
                $message = null;
        }
        if ($message) {
            $this->xasecoAdapter->chatSendServerMessage($message);
        }
    }

    /**
     * @param $xasecoRecord Record
     * @return UltimaniaRecord
     */
    private function mapXasecoRecordToUltiRecord(Record $xasecoRecord) {
        return new UltimaniaRecord(
            $xasecoRecord->player->login,
            $xasecoRecord->player->nickname,
            $xasecoRecord->score
        );
    }

    /**
     * @param UltimaniaRecord $ultimaniaRecord
     * @return array
     */
    private function mapUltiRecordToApiRecordDto(UltimaniaRecord $ultimaniaRecord) {
        return [
            'login' => $ultimaniaRecord->getLogin(),
            'nick' => $ultimaniaRecord->getNick(),
            'score' => $ultimaniaRecord->getScore(),
        ];
    }

    /**
     * @param $dto array
     * @return UltimaniaRecord
     */
    private function mapApiRecordDtoToUltiRecord($dto) {
        return new UltimaniaRecord(
            $dto['login'],
            $dto['nick'],
            $dto['score'],
            $dto['add_time']
        );
    }

    private function mapApiRecordDtosToUltiRecords($dtos) {
        return array_map('self::mapApiRecordDtoToUltiRecord', $dtos);
    }

    private function showPbWidgetToEveryone() {
        $recordsByLogin = $this->records->getRecordsIndexedByLogin();
        foreach ($this->xasecoAdapter->getPlayerList() as $p) {
            $this->pbWidgetShow($p, $recordsByLogin[$p->login]);
        }
    }
    
    /**
     * END HELPER METHODS
     ********************/
}
