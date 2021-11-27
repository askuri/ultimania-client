<?php

/**
 * Store and provide access to Ultimania Records independent of a map
 */
class UltimaniaRecords {
    /**
     * all records, always sorted in descending order
     * @var UltimaniaRecord[]
     */
    private $recordsOrderedByScore = [];

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
     * @param UltimaniaRecord[] $records
     * @return void
     */
    public function setAll($records) {
        $this->recordsOrderedByScore = $records;
    }

    /**
     * @return bool
     */
    public function isEmpty() {
        return empty($this->recordsOrderedByScore);
    }

    /**
     * @param UltimaniaRecord $newRecord
     * @return UltimaniaRecordImprovement
     */
    public function insertOrUpdate($newRecord) {
        $improvement = new UltimaniaRecordImprovement();

        $pointerToPreviousRecord = $this->getRecordByLogin($newRecord->getLogin());

        $improvement->setPreviousRecord($this->cloneIfIsObject($pointerToPreviousRecord));
        $improvement->setPreviousRank($this->getRankByLogin($newRecord->getLogin()));

        $this->updateScoreOfRecordIfImproved($pointerToPreviousRecord, $newRecord);
        usort($this->recordsOrderedByScore, "ulti_sortRecordsDesc");

        $improvement->setNewRecord($newRecord);
        $improvement->setNewRank($this->getRankByLogin($newRecord->getLogin()));

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
     * @param UltimaniaRecord|null $previousRecord
     * @param UltimaniaRecord $newRecord
     * @return void
     */
    private function updateScoreOfRecordIfImproved($previousRecord, UltimaniaRecord $newRecord) {
        if ($previousRecord instanceof UltimaniaRecord &&
            $newRecord->getScore() <= $previousRecord->getScore()
        ) {
            $previousRecord->setScore($newRecord->getScore());
        } else {
            $this->recordsOrderedByScore[] = $newRecord;
        }
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
     * @param mixed $obj
     * @return object|null
     */
    private function cloneIfIsObject($obj) {
        if (is_object($obj)) {
            return clone $obj;
        }
        return null;
    }
}
