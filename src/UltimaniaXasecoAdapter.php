<?php

class UltimaniaXasecoAdapter {

    /** @var Aseco */
    private $aseco;

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
     */
    public function chatSendServerMessage($message) {
        $this->aseco->client->query('ChatSendServerMessage', $message);
    }

    /**
     * @param String $message
     * @param Player $player
     */
    public function chatSendServerMessageToPlayer($message, Player $player) {
        $this->aseco->client->query('ChatSendServerMessageToLogin', $message, $player->login);
    }

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
     * @return Player
     */
    public function getPlayerObjectFromLogin($login) {
        return $this->getPlayerList()[$login];
    }

    /**
     * @param int $playerUid server's player uid
     * @param Player $player
     * @param int $action action id
     */
    public function releaseOnPlayerManialinkPageAnswerEvent($playerUid, Player $player, $action) {
        $this->aseco->releaseEvent('onPlayerManialinkPageAnswer',  array($playerUid, $player->login, $action));
    }

    /**
     * @param string $name
     * @param mixed $content
     */
    public function releaseEvent($name, $content) {
        $this->aseco->releaseEvent($name, $content);
    }

    /**
     * @param string $text
     */
    public function formatColors($text) {
        return $this->aseco->formatColors($text);
    }

    /**
     * @param Player $player
     * @param string $xml Manialink XML
     * @param int $autohideTimeout timeout in seconds
     * @param bool $hideOnClick
     */
    public function sendManialinkToPlayer($player, $xml, $autohideTimeout = 0, $hideOnClick = false) {
        $this->aseco->client->addCall('SendDisplayManialinkPageToLogin', array($player->login, $xml, $autohideTimeout, $hideOnClick));
    }

    /**
     * @param string $xml Manialink XML
     * @param int $autohideTimeout timeout in seconds
     * @param bool $hideOnClick
     */
    public function sendManialinkToEveryone($xml, $autohideTimeout = 0, $hideOnClick = false) {
        $this->aseco->client->addCall('SendDisplayManialinkPage', array($xml, $autohideTimeout, $hideOnClick));
    }

    /**
     * @param string $plugin filename of the plugin?
     * @param string $author
     * @param string $version
     */
    public function registerWithThirdpartyPluginsUpToDate($plugin, $author, $version) {
        $this->aseco->plugin_versions[] = array( /** @phpstan-ignore-line */
            'plugin' => $plugin,
            'author' => $author,
            'version' => $version
        );
    }
}
