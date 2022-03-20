<?php

/**
 * Represents a change (or no change) of a players
 * record between two points in time.
 *
 * previous = as was saved before player finished
 * new = result of what he just finished, may be worse than previous
 */
class UltimaniaRecordImprovement {
    const TYPE_NO_IMPROVEMENT = 'NO_IMPROVEMENT';
    const TYPE_NEW = 'NEW'; // secured
    const TYPE_EQUAL = 'EQUAL';
    const TYPE_NEW_RANK = 'NEW_RANK'; // gained
    const TYPE_FIRST = 'FIRST';
    const TYPE_UNKNOWN = 'UNKNOWN';

    /** @var int */
    private $previousRank;
    /** @var null|UltimaniaRecord */
    private $previousRecord;
    /** @var int */
    private $newRank;
    /** @var UltimaniaRecord */
    private $newRecord;

    /**
     * @return string
     */
    public function getType() {
        // special case where null can occur.
        // do this first to preven nullpointer exceptions in the other checks
        if ($this->hasPreviousRecord()) {
            return self::TYPE_FIRST;
        }

        if ($this->newRecord->getScore() < $this->previousRecord->getScore()) {
            return self::TYPE_NO_IMPROVEMENT;
        }
        if ($this->newRecord->getScore() > $this->previousRecord->getScore() &&
            $this->newRank == $this->previousRank) {
            return self::TYPE_NEW;
        }
        if ($this->newRecord->getScore() == $this->previousRecord->getScore()) {
            return self::TYPE_EQUAL;
        }
        if ($this->newRank < $this->previousRank) {
            return self::TYPE_NEW_RANK;
        }
        return self::TYPE_UNKNOWN;
    }

    /**
     * @return int Difference between new and previous score. Positive if improved.
     */
    public function getRecordDifference() {
        return $this->getNewRecord()->getScore() - $this->getNewRecord()->getScore();
    }

    /**
     * @return int
     */
    public function getPreviousRank() {
        return $this->previousRank;
    }

    /**
     * @param int $previousRank
     * @return void
     */
    public function setPreviousRank($previousRank) {
        $this->previousRank = $previousRank;
    }

    /**
     * @return UltimaniaRecord|null
     */
    public function getPreviousRecord() {
        return $this->previousRecord;
    }

    /**
     * @param UltimaniaRecord|null $previousRecord
     * @return void
     */
    public function setPreviousRecord($previousRecord) {
        $this->previousRecord = $previousRecord;
    }

    /**
     * @return int
     */
    public function getNewRank() {
        return $this->newRank;
    }

    /**
     * @param int $newRank
     * @return void
     */
    public function setNewRank($newRank) {
        $this->newRank = $newRank;
    }

    /**
     * @return UltimaniaRecord
     */
    public function getNewRecord() {
        return $this->newRecord;
    }

    /**
     * @param UltimaniaRecord $newRecord
     * @return void
     */
    public function setNewRecord($newRecord) {
        $this->newRecord = $newRecord;
    }

    /**
     * @return bool
     */
    private function hasPreviousRecord() {
        return empty($this->previousRecord);
    }
}
