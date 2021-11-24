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
     * @param $rank int Starting at 1
     * @return UltimaniaRecord
     */
    public function getRecordByRank($rank) {
        return $this->recordsOrderedByScore[$rank - 1];
    }

    /**
     * @param $records UltimaniaRecord[]
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
     * @param $recordInput UltimaniaRecord
     * @return UltimaniaRecordImprovement
     */
    public function insertOrUpdate1($recordInput) {
        $improvement = new UltimaniaRecordImprovement();
        $improvement->setNewRecord($recordInput);

        $hasExistingRecord = false;
        foreach ($this->recordsOrderedByScore as $id => $existingRecord) {
            $existingRank = $id + 1;
            if ($existingRecord->getLogin() == $recordInput->getLogin()) {
                $improvement->setPreviousRank($existingRank);
                $improvement->setPreviousRecord($existingRecord);
                $hasExistingRecord = true;
                if ($recordInput->getScore() > $existingRecord->getScore()) {
                    // new score is better (>) than existing score of same player
                    $this->recordsOrderedByScore[$id] = $recordInput;
                    if ($existingRank == 1) {
                        // if the existingRank is already 1, it can only be secured
                        $improvement->setType(UltimaniaRecordImprovement::TYPE_NEW);
                    } elseif ($this->getRecordByRank($existingRank - 1)->getScore() > $recordInput->getScore()) {
                        // there is a better record and its score is higher than the record we are processing here
                        $improvement->setType(UltimaniaRecordImprovement::TYPE_NEW);
                    } else {
                        // new record is higher than the record that was previously the next better one
                        $improvement->setType(UltimaniaRecordImprovement::TYPE_NEW_RANK);
                    }
                } elseif ($recordInput->getScore() == $existingRecord->getScore()) {
                    $improvement->setType(UltimaniaRecordImprovement::TYPE_EQUAL);
                }
            }
        }

        // doesn't have a record yet, so we insert it at the end
        if (!$hasExistingRecord) {
            $this->recordsOrderedByScore[] = $recordInput;
            $improvement->setType(UltimaniaRecordImprovement::TYPE_FIRST);
        }

        usort($this->recordsOrderedByScore, "ulti_sortRecordsDesc");

        return $improvement;
    }

    /**
     * @param $newRecord UltimaniaRecord
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
        return array_map(function ($record) {
            return [
                $record->getLogin() => $record->getScore(),
            ];
        }, $this->recordsOrderedByScore);
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
