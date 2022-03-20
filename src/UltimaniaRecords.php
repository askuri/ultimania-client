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
     * @param string $mapUid
     * @param string $replayContent
     * @return UltimaniaRecordImprovement
     */
    public function insertOrUpdate($newRecord, $mapUid, $replayContent) {
        $improvement = $this->localInsertOrUpdate($newRecord);

        if ($improvement->getType() != UltimaniaRecordImprovement::TYPE_NO_IMPROVEMENT) {
            $savedRecord = $this->ultiClient->submitRecord($newRecord, $mapUid);
            $this->getRecordByLogin($newRecord->getLogin())->setId($savedRecord->getId());
            $this->ultiClient->submitReplay($savedRecord->getId(), $replayContent);
        }

        return $improvement;
    }

    /**
     * @return array{string: UltimaniaRecord} {login: UltimaniaRecord}[]
     */
    public function getRecordsIndexedByLogin() {
        $indexedByLogin = [];
        foreach ($this->recordsOrderedByScore as $record) {
            $indexedByLogin[$record->getLogin()] = $record;
        }
        return $indexedByLogin;
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

        $referenceToPreviousRecord = $this->getRecordByLogin($newRecord->getLogin());

        $improvement->setPreviousRecord($this->cloneIfIsObject($referenceToPreviousRecord));
        $improvement->setPreviousRank($this->getRankByLogin($newRecord->getLogin()));

        $this->updateScoreOfRecordIfImprovedOrInsert($referenceToPreviousRecord, $newRecord);

        $improvement->setNewRecord($newRecord);
        $improvement->setNewRank($this->getRankByLogin($newRecord->getLogin()));

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
        usort($this->recordsOrderedByScore, "ulti_sortRecordsDesc");
    }

    /**
     * Returns the rank of a player or -1 if he doesn't have a record yet
     * @param string $login
     * @return int
     */
    private function getRankByLogin($login) {
        foreach ($this->getAll() as $i => $record) {
            if ($record->getLogin() === $login) {
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
