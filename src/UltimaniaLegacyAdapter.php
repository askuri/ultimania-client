<?php
/**
 * @deprecated use onUltimaniaRecordsLoadedApi2 and onUltimaniaRecordApi2
 */
class UltimaniaLegacyAdapter {
    /** @var Aseco */
    private $aseco;
    /** @var UltimaniaConfig */
    private $ultiConfig;
    /** @var UltimaniaRecords */
    private $ultiRecords;

    /**
     * @param UltimaniaConfig $ultiConfig
     * @param UltimaniaRecords $ultiRecords
     */
    public function __construct($ultiConfig, $ultiRecords) {
        global $aseco;
        $this->aseco = $aseco;
        $this->ultiConfig = $ultiConfig;
        $this->ultiRecords = $ultiRecords;
    }

    /**
     * @param string $name
     * @return array{'login': string, "nick": string, "score": int}[]|void
     */
    public function __get($name) {
        if ($name === 'records') {
            $ultiRecords = $this->ultiRecords->getLimitedBy($this->ultiConfig->getNumberOfRecordsDisplayLimit());
            return $this->mapUltimaniaRecordsToLegacyArray($ultiRecords);
        }
    }

    /**
     * @param UltimaniaRecord[] $records
     * @return void
     */
    public function mapRecordsLoadedEventToLegacyEvent($records) {
        $this->aseco->releaseEvent(ULTI_EVENT_RECORDS_LOADED_LEGACY, $this->mapUltimaniaRecordsToLegacyArray($records));
    }

    /**
     * @param UltimaniaRecord $record
     * @return void
     */
    public function mapRecordEventToLegacyEvent($record) {
        $this->aseco->releaseEvent(ULTI_EVENT_RECORD_LEGACY, $this->mapUltimaniaRecordToLegacyArray($record));
    }

    /**
     * @param UltimaniaRecord $record
     * @return array{'login': string, "nick": string, "score": int}
     */
    private function mapUltimaniaRecordToLegacyArray(UltimaniaRecord $record) {
        return [
            'login' => $record->getPlayer()->getLogin(),
            'nick' => $record->getPlayer()->getNick(),
            'score' => $record->getScore(),
        ];
    }

    /**
     * @param UltimaniaRecord[] $ultiRecords
     * @return array{'login': string, "nick": string, "score": int}[]
     */
    private function mapUltimaniaRecordsToLegacyArray($ultiRecords) {
        return array_map('self::mapUltimaniaRecordToLegacyArray', $ultiRecords);
    }
}
