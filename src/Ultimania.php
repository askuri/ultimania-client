<?php

class Ultimania {
    private $aseco;

    /** @var UltimaniaRecords */
    private $records;

    /** @var UltimaniaConfig */
    private $config;

    /** @var int timestamp of when the next refresh should happen */
    private $nextRefresh;

    /** @var string the actual API URL. URL is retrieved from ULTI_API_INFO URL */
    private $apiUrl;

    public function __construct($ultiConfig, $ultiRecords) {
        $this->config = $ultiConfig;
        $this->records = $ultiRecords;
        $this->nextRefresh = time(); // do it immediately, when plugin initiated
    }

    /**************************
     * BEGIN EVENT LISTENERS
     **/
    public function onSync($aseco) {
        $this->aseco = $aseco;

        $this->doEnvironmentChecks();
        $this->fetchAndSetApiUrl();
        $this->registerWithThirdpartyPluginsUpToDate();
    }

    public function onNewChallenge2($aseco, $chall_item) {
        // regularly check if there's a new API URL
        $this->fetchAndSetApiUrl();
        $this->refreshRecordsListAndReleaseEvent();

        // show pb widget to everyone
        $recordsByLogin = $this->records->getRecordsIndexedByLogin();
        foreach ($this->aseco->server->players->player_list as $p) {
            $this->pbWidgetShow($p, $recordsByLogin[$p->login]);
        }
    }

    public function onPlayerFinish(Aseco $aseco, Record $finish_item) {
        if ($finish_item->score == 0) return; // reject scores with 0 points

        $oldRecord = $this->records->getRecordsIndexedByLogin()[$finish_item->player->login];
        $newRecord = $this->mapXasecoRecordToUltiRecord($finish_item);

        $improvementType = $this->records->insertOrUpdate($newRecord);

        $response = $this->submitRecordToApi($newRecord);

        // We got the response from the server. Let's look if the player is banned
        if ($response['banned'] == true) {
            // Banned, so we punish him with this spam messages every finish
            $message = ('$f00Your Records won\'t be saved on Ultimania because you\'re banned (Reason: ' . $response['reason'] . ')');
            $aseco->client->query('ChatSendServerMessageToLogin', $message, $newRecord->getLogin());
            return;
        }

        $this->displayPlayerFinishChatMessage($finish_item->player, $oldRecord, $newRecord, $improvementType);

        // Not banned, so we throw event
        $this->releaseUltimaniaRecordEvent($newRecord);
    }

    public function onPlayerConnect($aseco, Player $player) {
        $banned_players = $this->fetchBannedPlayers();

        if ($banned_players == 'NULL') return;
        if (!is_array($banned_players)) return;

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
            chat_recpanel($aseco, $command);
        }

        // display global PB widget
        $recordsByLogin = $this->records->getRecordsIndexedByLogin();
        $this->pbWidgetShow($player, $recordsByLogin[$player->login]);
    }

    public function onEndRace1($aseco, $race) {
        $this->mainWindowHide(false); // Close window to all players
        $this->pbWidgetHide();
    }

    public function onEverySecond($aseco) {
        if (time() >= $this->nextRefresh) {
            $this->refreshRecordsListAndReleaseEvent();

            $this->nextRefresh = time() + $this->config->getRefreshInterval();
        }
    }

    public function onMlAnswer($aseco, $answer) {
        $player = $this->getPlayerObjectFromLogin($answer[1]);
        $actionId = $answer[2];

        switch ($actionId) {
            case ULTI_ID_PREFIX . 101:
                // Close Records Eyepiece windows
                $aseco->releaseEvent('onPlayerManialinkPageAnswer', array($answer[0], $player->login, 91800));

                // Close standard windows like /list
                $aseco->releaseEvent('onPlayerManialinkPageAnswer', array($answer[0], $player->login, 0));

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
                    var_dump($rank);

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
            $this->aseco->client->query('ChatSendServerMessageToLogin', '$ff0> $f00Usage: $bbb/ultirankinfo ranknumber$f00  (e.g. $bbb/ultirankinfo 1$f00)', $player->login);
            return;
        }

        $rank = $command['params'] - 1;

        $this->showUltiRankInfo($player, $rank);
    }

    public function onChatUltiWindow($command) {
        $this->mainWindowShow($command['author']);
    }

    public function onChatUltiUpdate($command) {
        $login = $command['author']->login;
        if ($this->aseco->isMasterAdminL($login)) {
            $newest = $this->fetchNewestAvailableUltimaniaClientVersion();
            if (version_compare(ULTI_VERSION, $newest) == -1) {
                $content = file_get_contents(ULTI_API_INFO . 'tmf/versions/' . $newest . '.php_');

                if ($content) {
                    if (!file_put_contents(getcwd() . '/plugins/plugin.ultimania.php', $content)) {
                        $this->aseco->client->query('ChatSendServerMessageToLogin', '$f00Unable to replace "plugin.ultimania.php". Please check the file-rights and try again.', $login);
                    } else {
                        $this->aseco->client->query('ChatSendServerMessageToLogin', '$0f0Successfully updated. Please restart XAseco', $login);
                    }
                } else {
                    $this->aseco->client->query('ChatSendServerMessageToLogin', '$f00Getting newest version file failed!', $login);
                }
            } else {
                $this->aseco->client->query('ChatSendServerMessageToLogin', '$0f0No update available', $login);
            }
        } else {
            $this->aseco->client->query('ChatSendServerMessageToLogin', '$f00No permissions!', $login);
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
        if (!$this->aseco->server->challenge->uid) {
            return;
        }

        $params_predefined = [ // These are send on all requests
            'action' => $action,
            'uid' => $this->aseco->server->challenge->uid,
            'mapname' => $this->aseco->server->challenge->name,
            'stunt' => 1,
            'server' => $this->aseco->server->serverlogin,
            'servername' => $this->aseco->server->nickname
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
            $this->aseco->console($responseJson);
        }

        if (!empty($response['error'])) {
            $this->aseco->console('[Ultimania] Error occurred on action ' . $params['action'] . ': ' . $response['error']);
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

        $this->aseco->client->addCall('SendDisplayManialinkPageToLogin', array($player->login, $xml, 0, false));
    }

    /**
     * @param $player Player|null
     */
    private function mainWindowHide($player) {
        $xml = '<manialink id="ultimania_window"></manialink>';

        if ($player === null) {
            $this->aseco->client->addCall('SendDisplayManialinkPage', array($xml, 0, false));
        } else {
            $this->aseco->client->addCall('SendDisplayManialinkPageToLogin', array($player->login, $xml, 0, false));
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

        $this->aseco->client->addCall('SendDisplayManialinkPageToLogin', array($player->login, $xml, 0, false));
    }

    private function pbWidgetHide() {
        $xml = '<manialink id="ultimania_pbwidget"></manialink>';
        $this->aseco->client->addCall('SendDisplayManialinkPage', array($xml, 0, false));
    }

    private function showFullRecordList(Player $player) {
        if ($this->records->isEmpty()) {
            $this->aseco->client->query('ChatSendServerMessageToLogin', '$06fNo Records. Feel free to drive the first :)', $player->login);
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
            $this->aseco->client->query('ChatSendServerMessageToLogin', '$ff0>> $f00This record does not exists', $showToPlayer->login);
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
            array('$h[ulti:admin_rec?uid=' . $this->aseco->server->challenge->uid . '&login=' . $record->getLogin() . ']Admin')
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

        if ($this->aseco->server->getGame() != 'TMF') {
            trigger_error('[Ultimania] This plugin supports TMF only. Can not start with a "' . $this->aseco->server->getGame() . '" Dedicated-Server!', E_USER_ERROR);
        }

        if ($this->aseco->server->gameinfo->mode != 4) {
            trigger_error('[Ultimania] This plugin supports stunts game mode only. Current game mode is: ' . $this->aseco->server->gameinfo->mode, E_USER_ERROR);
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

    /**
     * @param $login
     * @return mixed
     */
    private function getPlayerObjectFromLogin($login) {
        return $this->aseco->server->players->player_list[$login];
    }

    // Register this to the global version pool (for up-to-date checks)
    private function registerWithThirdpartyPluginsUpToDate() {
        $this->aseco->plugin_versions[] = array(
            'plugin' => 'plugin.ultimania.php',
            'author' => 'askuri',
            'version' => ULTI_VERSION
        );
    }

    private function releaseRecordsLoadedEvent() {
        $this->aseco->releaseEvent(ULTI_EVENT_RECORDS_LOADED_API2, $this->records->getAll());
    }

    private function releaseUltimaniaRecordEvent(UltimaniaRecord $record) {
        $this->aseco->releaseEvent(ULTI_EVENT_RECORD_API2, $record);
    }

    private function refreshRecordsListAndReleaseEvent() {
        $this->records->setAll($this->fetchRecordsFromServer());
        $this->releaseRecordsLoadedEvent();
    }

    /**
     * @param $oldRecord UltimaniaRecord|null
     * @param $improvementType 'NO_IMPROVEMENT'|'NEW'|'EQUAL'|'NEW_RANK'|'FIRST'
     */
    private function displayPlayerFinishChatMessage(Player $player, $oldRecord, UltimaniaRecord $newRecord, $improvementType) {
        switch ($improvementType) {
            case 'NEW':
                $this->aseco->client->query('ChatSendServerMessage', 'NEW - secured');
                break;
            case 'EQUAL':
                $this->aseco->client->query('ChatSendServerMessage', 'EQUAL - equaled');
                break;
            case 'NEW_RANK':
                $this->aseco->client->query('ChatSendServerMessage', 'NEW_RANK - gained');
                break;
            case 'FIRST':
                $this->aseco->client->query('ChatSendServerMessage', 'FIRST - claimed');
                break;
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

    /**
     * END HELPER METHODS
     ********************/
}
