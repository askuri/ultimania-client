<?php


use PHPUnit\Framework\TestCase;

class UltimaniaRecordImprovementTest extends TestCase {
    public function testGetTypeReturnsFirst() {
        $improvement = new UltimaniaRecordImprovement();
        $improvement->setPreviousRank(-1);
        $improvement->setPreviousRecord(null);
        $improvement->setNewRank(20);
        $improvement->setNewRecord($this->buildRecordWithScore(100));

        $this->assertEquals(UltimaniaRecordImprovement::TYPE_FIRST, $improvement->getType());
    }

    public function testGetTypeReturnsNew() {
        $improvement = new UltimaniaRecordImprovement();
        $improvement->setPreviousRank(20);
        $improvement->setPreviousRecord($this->buildRecordWithScore(100));
        $improvement->setNewRank(20);
        $improvement->setNewRecord($this->buildRecordWithScore(101));

        $this->assertEquals(UltimaniaRecordImprovement::TYPE_NEW, $improvement->getType());
    }

    public function testGetTypeReturnsNewRank() {
        $improvement = new UltimaniaRecordImprovement();
        $improvement->setPreviousRank(20);
        $improvement->setPreviousRecord($this->buildRecordWithScore(100));
        $improvement->setNewRank(15);
        $improvement->setNewRecord($this->buildRecordWithScore(150));

        $this->assertEquals(UltimaniaRecordImprovement::TYPE_NEW_RANK, $improvement->getType());
    }

    public function testGetTypeReturnsEqual() {
        $improvement = new UltimaniaRecordImprovement();
        $improvement->setPreviousRank(20);
        $improvement->setPreviousRecord($this->buildRecordWithScore(100));
        $improvement->setNewRank(20);
        $improvement->setNewRecord($this->buildRecordWithScore(100));

        $this->assertEquals(UltimaniaRecordImprovement::TYPE_EQUAL, $improvement->getType());
    }

    public function testGetTypeReturnsNoImprovement() {
        $improvement = new UltimaniaRecordImprovement();
        $improvement->setPreviousRank(20);
        $improvement->setPreviousRecord($this->buildRecordWithScore(100));
        $improvement->setNewRank(20);
        $improvement->setNewRecord($this->buildRecordWithScore(50));

        $this->assertEquals(UltimaniaRecordImprovement::TYPE_NO_IMPROVEMENT, $improvement->getType());
    }

    private function buildRecordWithScore($score) {
        return new UltimaniaRecord('login', 'map_uid', $score);
    }
}
