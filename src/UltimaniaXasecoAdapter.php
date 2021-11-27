<?php

class UltimaniaXasecoAdapter {

    /** @var Aseco */
    private $aseco;

    /**
     *
     */
    public function __construct() {
        global $aseco;

        $this->aseco = $aseco;
    }

    /**
     * In exceptional cases, direct acces to Aseco is required.
     * Avoid using this method!
     * @return Aseco
     */
    public function getAsecoObject() {
        return $this->aseco;
    }

    /**
     * @param string $message
     * @return void
     */
    public function console($message) {
        $this->aseco->console($message);
    }

    /**
     * @return bool
     */
    public function isXasecoStartingUp() {
        return $this->aseco->startup_phase;
    }

    /**
     * @param String $message
     * @return void
     */
    public function chatSendServerMessage($message) {
        $this->aseco->client->query('ChatSendServerMessage', $message);
    }

    /**
     * @param String $message
     * @param Player $player
     * @return void
     */
    public function chatSendServerMessageToPlayer($message, Player $player) {
        $this->aseco->client->query('ChatSendServerMessageToLogin', $message, $player->login);
    }

    /**
     * @param Player $player
     * @return bool
     */
    public function isMasterAdmin(Player $player) {
        return $this->aseco->isMasterAdmin($player);
    }

    /**
     * @return Challenge
     */
    public function getCurrentChallengeObject() {
        return $this->aseco->server->challenge;
    }

    /**
     * @return string
     */
    public function getGameType() {
        return $this->aseco->server->getGame();
    }

    /**
     * @return Gameinfo
     */
    public function getGameInfo() {
        return $this->aseco->server->gameinfo;
    }

    /**
     * @return Server
     */
    public function getServerObject() {
        return $this->aseco->server;
    }


    /**
     * @return Player[]
     */
    public function getPlayerList() {
        return $this->aseco->server->players->player_list;
    }

    /**
     * @param string $login
     * @return Player
     */
    public function getPlayerObjectFromLogin($login) {
        return $this->getPlayerList()[$login];
    }

    /**
     * @param int $playerUid server's player uid
     * @param Player $player
     * @param int $action action id
     * @return void
     */
    public function releaseOnPlayerManialinkPageAnswerEvent($playerUid, Player $player, $action) {
        $this->aseco->releaseEvent('onPlayerManialinkPageAnswer',  array($playerUid, $player->login, $action));
    }

    /**
     * @param string $name
     * @param mixed $content
     * @return void
     */
    public function releaseEvent($name, $content) {
        $this->aseco->releaseEvent($name, $content);
    }

    /**
     * @param string $text
     * @return string
     */
    public function formatColors($text) {
        return $this->aseco->formatColors($text);
    }

    /**
     * @param Player $player
     * @param string $xml Manialink XML
     * @param int $autohideTimeout timeout in seconds
     * @param bool $hideOnClick
     * @return void
     */
    public function sendManialinkToPlayer($player, $xml, $autohideTimeout = 0, $hideOnClick = false) {
        $this->aseco->client->addCall('SendDisplayManialinkPageToLogin', array($player->login, $xml, $autohideTimeout, $hideOnClick));
    }

    /**
     * @param string $xml Manialink XML
     * @param int $autohideTimeout timeout in seconds
     * @param bool $hideOnClick
     * @return void
     */
    public function sendManialinkToEveryone($xml, $autohideTimeout = 0, $hideOnClick = false) {
        $this->aseco->client->addCall('SendDisplayManialinkPage', array($xml, $autohideTimeout, $hideOnClick));
    }

    /**
     * @param string $plugin filename of the plugin?
     * @param string $author
     * @param string $version
     * @return void
     */
    public function registerWithThirdpartyPluginsUpToDate($plugin, $author, $version) {
        $this->aseco->plugin_versions[] = array( /** @phpstan-ignore-line */
            'plugin' => $plugin,
            'author' => $author,
            'version' => $version
        );
    }
}
