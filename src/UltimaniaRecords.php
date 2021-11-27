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
     * @return array [ login1 => score1, login2 => score2 ]
     */
    public function getRecordsIndexedByLogin() {
        $indexedByLogin = [];
        foreach ($this->recordsOrderedByScore as $record) {
            $indexedByLogin[$record->getLogin()] = $record->getScore();
        }
        return $indexedByLogin;
    }

    /**
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

    private function cloneIfIsObject($pointerToPreviousRecord) {
        if (is_object($pointerToPreviousRecord)) {
            return clone $pointerToPreviousRecord;
        }
        return null;
    }
}
