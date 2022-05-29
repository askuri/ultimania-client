<?php

/**
 * Store and provide access to Ultimania Records independent of a map
 */
class UltimaniaRecords {

    /** @var UltimaniaClient */
    private $ultiClient;

    /**
     * all records, always sorted in descending order
     * @var UltimaniaRecord[]
     */
    private $recordsOrderedByScore = [];

    /**
     * @param UltimaniaClient $ultiClient
     */
    public function __construct($ultiClient) {
        $this->ultiClient = $ultiClient;
    }

    /**
     * @return UltimaniaRecord[]
     */
    public function getAll() {
        return $this->recordsOrderedByScore;
    }

    /**
     * Return the best $limit records
     * @param int $limit
     * @return UltimaniaRecord[]
     */
    public function getLimitedBy($limit) {
        return array_slice($this->recordsOrderedByScore, 0, $limit);
    }

    /**
     * @param int $rank Starting at 1
     * @return UltimaniaRecord|null
     */
    public function getRecordByRank($rank) {
        if (isset($this->recordsOrderedByScore[$rank - 1])) {
            return $this->recordsOrderedByScore[$rank - 1];
        }
        return null;
    }

    /**
     * @param string $login
     * @return int|false
     */
    public function getRankOfPlayer($login) {
        $playerRecords = array_filter($this->recordsOrderedByScore, function($record) use ($login) {
            return $record->getPlayer()->getLogin() == $login;
        });

        if (empty($playerRecords)) {
            return false;
        }

        return array_keys($playerRecords)[0] + 1;
    }

    /**
     * @return bool
     */
    public function isEmpty() {
        return empty($this->recordsOrderedByScore);
    }

    /**
     * @param string $forMapUid
     * @return void
     */
    public function refresh($forMapUid) {
        $this->recordsOrderedByScore = $this->ultiClient->getRecords($forMapUid);
    }

    /**
     * @param UltimaniaRecord $newRecord
     * @param string $replayContent
     * @return UltimaniaRecordImprovement
     */
    public function saveRecord($newRecord, $replayContent) {
        $improvement = $this->localInsertOrUpdate($newRecord);

        if ($improvement->getType() != UltimaniaRecordImprovement::TYPE_NO_IMPROVEMENT) {
            $savedRecord = $this->ultiClient->submitRecord($newRecord, $newRecord->getMapUid());
            $replayAvailable = $this->ultiClient->submitReplay($savedRecord->getId(), $replayContent)['replay_available'];

            // post-saving updates
            $referenceToLocallySavedRecord = $this->getRecordByLogin($newRecord->getPlayer()->getLogin());
            $referenceToLocallySavedRecord->setId($savedRecord->getId());
            $referenceToLocallySavedRecord->setReplayAvailable($replayAvailable);
        }

        return $improvement;
    }

    /**
     * @return array{string: UltimaniaRecord} {login: UltimaniaRecord}[]
     */
    public function getRecordsIndexedByLogin() {
        $indexedByLogin = [];
        foreach ($this->recordsOrderedByScore as $record) {
            $indexedByLogin[$record->getPlayer()->getLogin()] = $record;
        }
        return $indexedByLogin; /* @phpstan-ignore-line It doesn't really get the return type is actually correct */
    }

    /**
     * @param string $login
     * @return UltimaniaRecord|null
     */
    public function getRecordByLogin($login) {
        $records = $this->getRecordsIndexedByLogin();
        if (isset($records[$login])) {
            return $records[$login];
        }
        return null;
    }

    /**
     * @param UltimaniaRecord $newRecord
     * @return UltimaniaRecordImprovement
     */
    private function localInsertOrUpdate($newRecord) {
        $improvement = new UltimaniaRecordImprovement();

        $referenceToPreviousRecord = $this->getRecordByLogin($newRecord->getPlayer()->getLogin());

        $improvement->setPreviousRecord($this->cloneIfIsObject($referenceToPreviousRecord));
        $improvement->setPreviousRank($this->getRankByLogin($newRecord->getPlayer()->getLogin()));

        $this->updateScoreOfRecordIfImprovedOrInsert($referenceToPreviousRecord, $newRecord);

        $improvement->setNewRecord($newRecord);
        $improvement->setNewRank($this->getRankByLogin($newRecord->getPlayer()->getLogin()));

        return $improvement;
    }

    /**
     * @param UltimaniaRecord|null $previousRecord
     * @param UltimaniaRecord $newRecord
     * @return void
     */
    private function updateScoreOfRecordIfImprovedOrInsert($previousRecord, UltimaniaRecord $newRecord) {
        if ($previousRecord instanceof UltimaniaRecord) {
            if ($newRecord->getScore() > $previousRecord->getScore()) {
                $previousRecord->setScore($newRecord->getScore());
            }
        } else {
            $this->recordsOrderedByScore[] = $newRecord;
        }
        usort($this->recordsOrderedByScore, "self::sortRecordsDescCallback"); /* @phpstan-ignore-line PHPStand doesn't understand this string is a callback. */
    }

    /**
     * usort callback for records.
     * Use with usort($array, 'ulti_sortRecordsDesc')
     * @param UltimaniaRecord $a
     * @param UltimaniaRecord $b
     * @return int
     */
    function sortRecordsDescCallback(UltimaniaRecord $a, UltimaniaRecord $b) {
        if ($a->getScore() == $b->getScore()) {
            return 0;
        }

        return ($a->getScore() > $b->getScore()) ? -1 : 1;
    }

    /**
     * Returns the rank of a player or -1 if he doesn't have a record yet
     * @param string $login
     * @return int
     */
    private function getRankByLogin($login) {
        foreach ($this->getAll() as $i => $record) {
            if ($record->getPlayer()->getLogin() === $login) {
                return $i + 1;
            }
        }
        return -1;
    }

    /**
     * @template T
     * @param T $obj
     * @return T|null
     */
    private function cloneIfIsObject($obj) {
        if (is_object($obj)) {
            return clone $obj;
        }
        return null;
    }
}
