<?php

class UltimaniaRecord {
    private $login;
    private $nick;
    private $score;
    private $addTime;

    public function __construct($login, $nick, $score, $addTime = null) {
        $this->login = $login;
        $this->nick = $nick;
        $this->score = $score;
        $this->addTime = $addTime;
    }

    public function getLogin() {
        return $this->login;
    }

    public function getNick() {
        return $this->nick;
    }

    public function getScore() {
        return $this->score;
    }

    public function setScore($score) {
        $this->score = $score;
    }

    public function getAddTime() {
        return $this->addTime;
    }

    public function setAddTime($addTime) {
        $this->addTime = $addTime;
    }
}
