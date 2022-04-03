<?php

class UltimaniaPlayer {

    /** @var string */
    private $login;

    /** @var string */
    private $nick;

    /** @var bool|null */
    private $allowReplayDownload;

    /** @var bool|null */
    private $banned;

    /**
     * @param string $login
     * @param string $nick
     * @param bool|null $allowReplayDownload
     * @param bool|null $banned
     */
    public function __construct($login, $nick, $allowReplayDownload = null, $banned = null) {
        $this->login = $login;
        $this->nick = $nick;
        $this->allowReplayDownload = $allowReplayDownload;
        $this->banned = $banned;
    }

    /**
     * @return string
     */
    public function getLogin() {
        return $this->login;
    }

    /**
     * @return string
     */
    public function getNick() {
        return $this->nick;
    }

    /**
     * @return bool|null
     */
    public function isAllowReplayDownload() {
        return $this->allowReplayDownload;
    }

    /**
     * @param bool $allowReplayDownload
     * @return void
     */
    public function setAllowReplayDownload($allowReplayDownload) {
        $this->allowReplayDownload = $allowReplayDownload;
    }

    /**
     * @return bool|null
     */
    public function isBanned() {
        return $this->banned;
    }
}
