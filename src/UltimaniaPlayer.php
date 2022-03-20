<?php

class UltimaniaPlayer {

    /** @var string */
    private $login;

    /** @var string */
    private $nick;

    /** @var bool */
    private $banned;

    /**
     * @param string $login
     * @param string $nick
     * @param bool $banned
     */
    public function __construct($login, $nick, $banned) {
        $this->login = $login;
        $this->nick = $nick;
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
     * @return bool
     */
    public function isBanned() {
        return $this->banned;
    }
}
