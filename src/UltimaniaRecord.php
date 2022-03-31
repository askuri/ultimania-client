<?php

class UltimaniaRecord {

    /** @var string|null */
    private $id;

    /** @var UltimaniaPlayer|null null if object constructed with login only */
    private $player;

    /** @var string|null null if object constructed with a full player object */
    private $playerLogin;

    /** @var string */
    private $mapUid;

    /** @var int */
    private $score;

    /** @var int|null  */
    private $addTime;

    /** @var bool|null */
    private $replayAvailable;

    /** @var string|null */
    private $replay; // todo remove?

    /**
     * @param UltimaniaPlayer|string $player if string, only set the login
     * @param string $mapUid
     * @param int $score
     * @param int|null $addTime
     * @param string|null $id
     */
    public function __construct($player, $mapUid, $score, $addTime = null, $id = null, $replayAvailable = null) {
        if (is_string($player)) {
            $this->playerLogin = $player;
        } else {
            $this->player = $player;
        }
        $this->mapUid = $mapUid;
        $this->score = $score;
        $this->addTime = $addTime;
        $this->id = $id;
        $this->replayAvailable = $replayAvailable;
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
     * @return UltimaniaPlayer|null
     */
    public function getPlayer() {
        return $this->player;
    }

    /**
     * @return string|null
     */
    public function getPlayerLogin() {
        return $this->playerLogin;
    }

    /**
     * @return string
     */
    public function getMapUid() {
        return $this->mapUid;
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

    /**
     * @return bool|null
     */
    public function isReplayAvailable() {
        return $this->replayAvailable;
    }

    /**
     * @param bool $replayAvailable
     * @return void
     */
    public function setReplayAvailable($replayAvailable) {
        $this->replayAvailable = $replayAvailable;
    }

}
