<?php

class UltimaniaRecord {
    /** @var string */
    private $login;

    /** @var string */
    private $nick;

    /** @var int */
    private $score;

    /** @var int|null  */
    private $addTime;

    /**
     * @param string $login
     * @param string $nick
     * @param int $score
     * @param int|null $addTime
     */
    public function __construct($login, $nick, $score, $addTime = null) {
        $this->login = $login;
        $this->nick = $nick;
        $this->score = $score;
        $this->addTime = $addTime;
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
     * @return int
     */
    public function getScore() {
        return $this->score;
    }

    /**
     * @param int $score
     * @return void
     */
    public function setScore($score) {
        $this->score = $score;
    }

    /**
     * @return int|null
     */
    public function getAddTime() {
        return $this->addTime;
    }

    /**
     * @param int|null $addTime
     * @return void
     */
    public function setAddTime($addTime) {
        $this->addTime = $addTime;
    }
}
