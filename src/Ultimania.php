<?php

class Ultimania {
    /** @var UltimaniaRecords */
    private $records;

    /** @var UltimaniaConfig */
    private $config;

    /** @var UltimaniaXasecoAdapter */
    private $xasecoAdapter;

    /** @var UltimaniaClient */
    private $ultiClient;

    /** @var int timestamp of when the next refresh should happen */
    private $nextRefresh;

    /**
     * @param UltimaniaConfig $ultiConfig
     * @param UltimaniaRecords $ultiRecords
     * @param UltimaniaXasecoAdapter $xasecoAdapter
     * @param UltimaniaClient $client
     */
    public function __construct($ultiConfig, $ultiRecords, $xasecoAdapter, $client) {
        $this->config = $ultiConfig;
        $this->records = $ultiRecords;
        $this->xasecoAdapter = $xasecoAdapter;
        $this->ultiClient = $client;
        $this->nextRefresh = time() + $this->config->getRefreshInterval();
    }

    /**************************
     * BEGIN EVENT LISTENERS
     **/

    /**
     * @return void
     */
    public function onStartup() {
        $this->doEnvironmentChecks();
        $this->registerWithThirdpartyPluginsUpToDate();
    }

    /**
     * @param Challenge $map
     * @return void
     */
    public function onNewChallenge($map) {
        $this->ultiClient->registerOrUpdateMap($map);
        $this->refreshRecordsListAndReleaseEvent();
        $this->showPbWidgetToEveryone();
    }

    /**
     * @param Record $finish_item
     * @return void
     */
    public function onPlayerFinish(Record $finish_item) {
        if ($finish_item->score == 0) return;

        $ultiRecord = $this->mapXasecoRecordToUltiRecord($finish_item);
        $ultiRecord->setAddTime(time());

        $improvement = $this->records->saveRecord(
            $ultiRecord,
            $this->xasecoAdapter->getBestReplayForPlayer($finish_item->player)
        );

        $newRecord = $improvement->getNewRecord();

        $this->displayPlayerFinishChatMessage($finish_item->player, $improvement);

        $this->releaseUltimaniaRecordEvent($newRecord);
    }

    /**
     * @param Player $player
     * @return void
     */
    public function onPlayerConnect(Player $player) {
        $playerFromApi = $this->ultiClient->registerOrUpdatePlayer($this->mapXasecoPlayerToUltiPlayer($player));

        if ($playerFromApi->isBanned()) {
            $this->showBannedPlayerInfoWindow($player);
        }

        // Remove Dedimania PB widget in bottom-right corner
        if (function_exists('chat_recpanel')) {
            $command = [];
            $command['author'] = $player;
            $command['params'] = 'RightBottomNoDedi';
            chat_recpanel($this->xasecoAdapter->getAsecoObject(), $command);
        }

        // display global PB widget
        $personalBestRecord = $this->records->getRecordByLogin($player->login);
        $this->pbWidgetShow($player, $personalBestRecord);
    }

    /**
     * @return void
     */
    public function onEndRace() {
        $this->mainWindowHideToEveryone();
        $this->pbWidgetHide();
    }

    /**
     * @return void
     */
    public function onEverySecond() {
        if (time() >= $this->nextRefresh) {
            $this->refreshRecordsListAndReleaseEvent();

            $this->nextRefresh = time() + $this->config->getRefreshInterval();
        }
    }

    /**
     * @param int $playerUid
     * @param Player $player
     * @param int $actionId
     * @return void
     */
    public function onMlAnswer($playerUid, $player, $actionId) {
        switch ($actionId) {
            case ULTI_ID_PREFIX . 101:
                // Close Records Eyepiece windows
                $this->xasecoAdapter->releaseOnPlayerManialinkPageAnswerEvent($playerUid, $player, 91800);

                // Close standard windows like /list
                $this->xasecoAdapter->releaseOnPlayerManialinkPageAnswerEvent($playerUid, $player, 0);

                $this->mainWindowShow($player);
                break;

            case ULTI_ID_PREFIX . 102:
                $this->mainWindowHideToPlayer($player);
                break;

            case ULTI_ID_PREFIX . 104:
                $this->showFullRecordList($player);
                break;

            case ULTI_ID_PREFIX . 105:
                $this->ultiClient->toggleAllowReplayDownlod($player->login);

                // refresh the list so the player sees he can't even download his own replay
                $this->refreshRecordsListAndReleaseEvent();

                // refresh the window
                $this->mainWindowShow($player);
                break;

            default: // special cases
                // Prefix for recordinfos is 2
                if (substr((string) $actionId, 0, strlen(ULTI_ID_PREFIX) + 1) == ULTI_ID_PREFIX . 2) {
                    $rank = intval(substr((string) $actionId, strlen(ULTI_ID_PREFIX) + 1)) + 1;

                    $this->showUltiRankInfo($player, $rank);
                    $this->mainWindowHideToPlayer($player);
                }
        }
    }

    /**
     * @param Player $author
     * @return void
     */
    public function onChatUltiList(Player $author) {
        $this->showFullRecordList($author);
    }

    /**
     * @param Player $author
     * @param string $rank starting at 1
     * @return void
     */
    public function onChatUltiRankInfo(Player $author, $rank) {
        if (!is_numeric($rank)) {
            $this->xasecoAdapter->chatSendServerMessageToPlayer('$ff0> $f00Usage: $bbb/ultirankinfo ranknumber$f00  (e.g. $bbb/ultirankinfo 1$f00)', $author);
            return;
        }

        $this->showUltiRankInfo($author, (int) $rank);
    }

    /**
     * @param Player $author
     * @return void
     */
    public function onChatUltiWindow(Player $author) {
        $this->mainWindowShow($author);
    }

    /**
     * @param Player $author
     * @return void
     */
    public function onChatUltiUpdate(Player $author) {
        if ($this->xasecoAdapter->isMasterAdmin($author)) {
            $newestVersion = $this->ultiClient->fetchNewestAvailableUltimaniaClientVersion();
            if (version_compare(ULTI_PLUGIN_VERSION, $newestVersion) == -1) {
                $content = $this->ultiClient->fetchUpdate($newestVersion);

                if ($content) {
                    if (!file_put_contents(getcwd() . '/plugins/plugin.ultimania.php', $content)) {
                        $this->xasecoAdapter->chatSendServerMessageToPlayer('$f00Unable to replace "plugin.ultimania.php". Please check the file permissions and try again.', $author);
                    } else {
                        $this->xasecoAdapter->chatSendServerMessageToPlayer('$0f0Successfully updated. Please restart XAseco.', $author);
                    }
                } else {
                    $this->xasecoAdapter->chatSendServerMessageToPlayer('$f00Getting newest version file failed!', $author);
                }
            } else {
                $this->xasecoAdapter->chatSendServerMessageToPlayer('$0f0You are using the newest version.', $author);
            }
        } else {
            $this->xasecoAdapter->chatSendServerMessageToPlayer('$f00No permissions!', $author);
        }
    }

    /**
     * END EVENT LISTENERS
     **************************/

    /*************************************
     * BEGIN MANIALINKS
     */

    /**
     * @return void
     */
    private function mainWindowShow(Player $player) {
        $ultinfo = htmlspecialchars($this->ultiClient->fetchInfotextInMainWindow());

        $xml = '<manialink id="ultimania_window">
			
			<quad posn="0 3 0" sizen="79 54" bgcolor="001a" halign="center" valign="center" />
			<quad posn="0 3 2" sizen="81 56" style="Bgs1InRace" substyle="BgCard3" halign="center" valign="center" />
			
			<quad posn="0 29.5 1" sizen="82 5" style="BgsPlayerCard" substyle="BgRacePlayerLine" halign="center" />
			<quad posn="0 30 0.5" sizen="80 5" style="Bgs1InRace" substyle="BgTitle3_3" halign="center" />
			<label posn="0 29 2" style="TextRankingsBig" halign="center" text="Ultimania World Records" />
			
			<quad posn="33.5 30 3" sizen="5 5" style="Icons128x128_1" substyle="BackFocusable" action="5450102" />
			
			<label posn="-38 -23.65 3" text="$000$tUltimania TMF v' . ULTI_PLUGIN_VERSION . ' using API ' . ULTI_API_VERSION . '" textsize="0" />
		';

        $xml .= '
			<quad posn="-38 1 1" sizen="37 47" style="BgsPlayerCard" substyle="BgRacePlayerName" valign="center" />
			<quad posn="-0.5 24.5 1" sizen="38.5 41.5" style="BgsPlayerCard" substyle="BgRacePlayerName" />
			<quad posn="-0.5 -17.5 1" sizen="38.5 5" style="BgsPlayerCard" substyle="BgRacePlayerName" />
			
			<label posn="0 24 2" text="' . $ultinfo . '" textsize="2" />
		';

        $xml .= '<frame posn="-38.5 26.5 1">';

        if ($this->records->isEmpty()) {
            $xml .= '<label posn="1.8 -3 1" text="$oCurrently no records" textsize="2" />';
        } else {
            $x = 0;
            $y = -3;

            /**
             * @var int $i rank - 1
             * @var UltimaniaRecord $record
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
                $xml .= '<label posn="' . ($x + 8) . ' ' . $y . ' 1" text="' . $this->handleSpecialChars($record->getPlayer()->getNick()) . '" sizen="12 2" textsize="1" />';
                $xml .= '<label posn="' . ($x + 21) . ' ' . $y . ' 1" text="' . $record->getPlayer()->getLogin() . '" sizen="13 2" textsize="1" />';

                if ($record->isReplayAvailable()) {
                    $xml .= '<quad posn="' . ($x + 34.2) . ' ' . ($y + 0.2) . ' 2" sizen="1.8 1.8" style="Icons128x128_1" substyle="Replay" manialink="'.$this->ultiClient->getLinkForViewReplayManialink($record).'" />';
                }
                $xml .= '<quad posn="' . ($x + 35.8) . ' ' . ($y - 0.1) . ' 2" sizen="1.35 1.35" style="Icons64x64_1" substyle="TrackInfo" action="' . ULTI_ID_PREFIX . 2 . $i . '" />';

                if ($record->getPlayer()->getLogin() == $player->login) {
                    $xml .= '<quad posn="' . ($x + 0.7) . ' ' . ($y + 0.2) . ' 0.5" sizen="36.65 2" style="BgsPlayerCard" substyle="ProgressBar" />';
                }

                $y -= 1.83;
            }
        }
        $xml .= '</frame>';

        $xml .= '<frame posn="0.2 -20 0">';
        $allowReplayDownload = $this->ultiClient->getPlayerInfo($player->login)->isAllowReplayDownload();
        $allowReplayDownloadSubstyle = $allowReplayDownload == true ? "LvlGreen" : "LvlRed";
        $xml .= '<quad posn="0.4 0 5" sizen="1.5 1.5" style="Icons64x64_1" substyle="' . $allowReplayDownloadSubstyle . '" valign="center"/>';
        $xml .= '<label posn="0 0 1" style="CardButtonSmallWide" text="Allow players to view my replays" valign="center" scale="0.77" action="' . ULTI_ID_PREFIX . 105 . '"/>';
        $xml .= '</frame>';

        $xml .= '</manialink>';

        $this->xasecoAdapter->sendManialinkToPlayer($player, $xml);
    }

    /**
     * @param Player $player
     * @return void
     */
    private function mainWindowHideToPlayer($player) {
        $xml = '<manialink id="ultimania_window"></manialink>';
        $this->xasecoAdapter->sendManialinkToPlayer($player, $xml);
    }

    /**
     * @return void
     */
    private function mainWindowHideToEveryone() {
        $xml = '<manialink id="ultimania_window"></manialink>';
        $this->xasecoAdapter->sendManialinkToEveryone($xml);
    }

    /**
     * Record may be null, so we need the Player object to show a "no record yet" widget to the player
     * @param Player $player
     * @param UltimaniaRecord|null $record
     * @return void
     */
    private function pbWidgetShow($player, $record) {
        if (empty($record)) {
            $pbText = '---';
        } else {
            $pbText = $record->getScore();
        }

        $xml = '<manialink id="ultimania_pbwidget">
			<frame posn="53.5 -32.7 0">
                <label posn="4.5 -6.2 2" sizen="10 2" halign="right" valign="center" textsize="1" textcolor="dddf" text="Ultimania PB:"/>
                <label posn="5.0 -6.2 2" sizen="6.5 2" halign="left" valign="center" textsize="1" textcolor="ffff" text="  ' . $pbText . '" action="' . ULTI_ID_PREFIX . '104"/>
			</frame>
		</manialink>';

        $this->xasecoAdapter->sendManialinkToPlayer($player, $xml);
    }

    /**
     * @return void
     */
    private function pbWidgetHide() {
        $xml = '<manialink id="ultimania_pbwidget"></manialink>';
        $this->xasecoAdapter->sendManialinkToEveryone($xml);
    }

    /**
     * @param Player $player
     * @return void
     */
    private function showFullRecordList(Player $player) {
        if ($this->records->isEmpty()) {
            $this->xasecoAdapter->chatSendServerMessageToPlayer('$06fNo Records. Feel free to drive the first :)', $player);
            return;
        }

        $header = 'Ultimania records:'; // Window Header

        $player->msgs = [];

        // Settings
        $recordsPerPage = 15;
        $rankOfPlayer = $this->records->getRankOfPlayer($player->login);
        $startpage = $rankOfPlayer === false ? 1 : ceil($rankOfPlayer / $recordsPerPage);
        $player->msgs[0] = [$startpage,
            $header,
            array(1.4, 0.1, 0.15, 0.3, 0.35, 0.3, 0.2), // widths: overall, col1, col2, col3, ...
            array('Icons64x64_1', 'TrackInfo') // icon style
        ];

        $page = 1;
        foreach ($this->records->getAll() as $id => $record) {
            $time = $this->timestampToDateTimeStringOrGery($record->getAddTime());
            $playerHighlight = $record->getPlayer()->getLogin() == $player->login ? '{#logina}' : '';
            $player->msgs[$page][] = [
                $id + 1,
                $record->getScore(),
                $record->getPlayer()->getNick(),
                $playerHighlight . $record->getPlayer()->getLogin(),
                $time,
                $record->isReplayAvailable() ? '$h['.$this->ultiClient->getLinkForViewReplayManialink($record).']Replay' : '',
            ];

            if ((($id + 1) % $recordsPerPage) == 0) $page++;
        }

        // display ManiaLink message
        display_manialink_multi($player);
    }

    /**
     * @param Player $showToPlayer player to show the information to
     * @param int $rank starting from 1. Rank that information should be shown about
     * @return void
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
            array('Login', '$FFC' . $record->getPlayer()->getLogin()),
            array('Nickname', '$FFC' . $record->getPlayer()->getNick()),
            array('Recorded on', '$FFC' . $dateTimeStringOrGery),
            array('$h[' . $this->ultiClient->getLinkForAdminRecManialink($record->getPlayer(), $this->xasecoAdapter->getCurrentChallengeObject()->uid) . ']Admin')
        );

        // display ManiaLink message
        display_manialink_multi($showToPlayer);
    }

    /**
     * @param Player $player
     * @return void
     */
    private function showBannedPlayerInfoWindow(Player $player) {
        $header = 'Ultimania global record database information:';
        $data = array();
        $data[] = array('You\'re banned from the global records database Ultimania.');
        $data[] = array('');
        $data[] = array('That means:');
        $data[] = array('- Your record won\'t be saved on Ultimania');
        $data[] = array('- You will always see this window when joining a server');
        $data[] = array('');
        $data[] = array('You can be unbanned by writing an apology for whatever you\'ve done');
        $data[] = array('(cheating, hacking, ...) to askuri (enwi2@t-online.de)');
        $data[] = array('If he thinks you regret whatever you did, he\'ll unban you.');
        $data[] = array('');
        $data[] = array('Best regards');
        display_manialink($player->login, $header, array('Icons64x64_1', 'TrackInfo', -0.01), $data, array(1.1), 'OK');
    }

    /**
     * END MANIALINKS
     *************************************/

    /**********************
     * BEGIN HELPER METHODS
     */

    // stolen from records_eyepiece
    /**
     * @param string $string
     * @return string
     */
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

    /**
     * @return void
     */
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

    /**
     * @param int $timestamp
     * @return string
     */
    private function timestampToDateTimeStringOrGery($timestamp) {
        // Geryimports were done on 2013/24/03 and other dates. I keep searching them
        if ($timestamp > 1364148372 and $timestamp <= 1367798399) {
            $time = 'Gerymania Import';
        } else {
            $time = date('M j Y G:i', $timestamp);
        }

        return $time;
    }

    /**
     * Register this to the global version pool (for up-to-date checks)
     * @return void
     */
    private function registerWithThirdpartyPluginsUpToDate() {
        $this->xasecoAdapter->registerWithThirdpartyPluginsUpToDate(
            'plugin.ultimania.php',
            'askuri',
            ULTI_PLUGIN_VERSION
        );
    }

    /**
     * @return void
     */
    private function releaseRecordsLoadedEvent() {
        $this->xasecoAdapter->releaseEvent(ULTI_EVENT_RECORDS_LOADED_API2, $this->records->getAll());
    }

    /**
     * @param UltimaniaRecord $record
     * @return void
     */
    private function releaseUltimaniaRecordEvent(UltimaniaRecord $record) {
        $this->xasecoAdapter->releaseEvent(ULTI_EVENT_RECORD_API2, $record);
    }

    /**
     * @return void
     */
    private function refreshRecordsListAndReleaseEvent() {
        $this->records->refresh($this->xasecoAdapter->getCurrentChallengeObject()->uid);
        $this->releaseRecordsLoadedEvent();
    }

    /**
     * @param Player $player Player who drove the record
     * @param UltimaniaRecordImprovement $improvement
     * @return void
     */
    private function displayPlayerFinishChatMessage(Player $player, UltimaniaRecordImprovement $improvement) {
        $message = $this->generatePlayerFinishMessage($player, $improvement);

        if ($message) {
            if ($improvement->getNewRank() <= $this->config->getNumberOfRecordsDisplayLimit()) {
                // only show the message publicly, if it's within the display limit
                $this->xasecoAdapter->chatSendServerMessage($this->xasecoAdapter->formatColors($message));
            } else {
                // if the score is not that good, show it to the player though
                $message = str_replace('{#server}>> ', '{#server}> ', $message);
                $this->xasecoAdapter->chatSendServerMessageToPlayer($this->xasecoAdapter->formatColors($message), $player);
            }
        }
    }

    /**
     * Public only for testing. Would be too much work to actually fix the architectural issue behind that.
     * @param Player $player Player who drove the record
     * @param UltimaniaRecordImprovement $improvement
     * @return string|null message or null if unknown record type
     */
    public function generatePlayerFinishMessage($player, UltimaniaRecordImprovement $improvement) {
        $nicknameWithoutColors = stripColors($player->nickname);
        switch ($improvement->getType()) {
            case UltimaniaRecordImprovement::TYPE_NEW:
                return formatText($this->config->getMessageRecordNew(),
                    $nicknameWithoutColors,
                    $improvement->getNewRank(),
                    $improvement->getNewRecord()->getScore(),
                    $improvement->getPreviousRank(),
                    $improvement->getRecordDifference()
                );
            case UltimaniaRecordImprovement::TYPE_EQUAL:
                return formatText($this->config->getMessageRecordEqual(),
                    $nicknameWithoutColors,
                    $improvement->getNewRank(),
                    $improvement->getNewRecord()->getScore()
                );
            case UltimaniaRecordImprovement::TYPE_NEW_RANK:
                return formatText($this->config->getMessageRecordNewRank(),
                    $nicknameWithoutColors,
                    $improvement->getNewRank(),
                    $improvement->getNewRecord()->getScore(),
                    $improvement->getPreviousRank(),
                    $improvement->getRecordDifference()
                );
            case UltimaniaRecordImprovement::TYPE_FIRST:
                return formatText($this->config->getMessageRecordFirst(),
                    $nicknameWithoutColors,
                    $improvement->getNewRank(),
                    $improvement->getNewRecord()->getScore()
                );
            default:
                return null;
        }
    }

    /**
     * @param Record $xasecoRecord
     * @return UltimaniaRecord
     */
    private function mapXasecoRecordToUltiRecord(Record $xasecoRecord) {
        return new UltimaniaRecord(
            $this->mapXasecoPlayerToUltiPlayer($xasecoRecord->player),
            $xasecoRecord->challenge->uid,
            $xasecoRecord->score
        );
    }

    /**
     * @param Player $xasecoPlayer
     * @return UltimaniaPlayer
     */
    private function mapXasecoPlayerToUltiPlayer(Player $xasecoPlayer) {
        return new UltimaniaPlayer($xasecoPlayer->login, $xasecoPlayer->nickname);
    }

    /**
     * @return void
     */
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
