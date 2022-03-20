<?php

class UltimaniaRecord {

    /** @var string|null */
    private $id;

    /** @var string */
    private $login; // todo rename to player_login

    /** @var string */
    private $map_uid;

    /** @var string @deprecated */
    private $nick; // todo remove

    /** @var int */
    private $score;

    /** @var int|null  */
    private $addTime;

    /** @var string|null */
    private $replay; // todo remove?

    /**
     * @param string $login
     * @param string $nick
     * @param int $score
     * @param int|null $addTime
     * @param string|null $id
     */
    public function __construct($login, $nick, $score, $addTime = null, $id = null) {
        $this->login = $login;
        $this->nick = $nick;
        $this->score = $score;
        $this->addTime = $addTime;
        $this->id = $id;
    }

    /**
     * @return string|null
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @param string|null $id
     * @return void
     */
    public function setId($id) {
        $this->id = $id;
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
    public function getMapUid() {
        return $this->map_uid;
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

    /**
     * @return string|null
     */
    public function getReplay() {
        return $this->replay;
    }

    /**
     * @param string|null $replay
     * @return void
     */
    public function setReplay($replay) {
        $this->replay = $replay;
    }

}
