<?php
/**
 * @deprecated
 */
class UltimaniaLegacyAdapter {
    /** @var Aseco */
    private $aseco;
    /** @var UltimaniaConfig */
    private $ultiConfig;
    /** @var UltimaniaRecords */
    private $ultiRecords;

    public function __construct($ultiConfig, $ultiRecords) {
        global $aseco;
        $this->aseco = $aseco;
        $this->ultiConfig = $ultiConfig;
        $this->ultiRecords = $ultiRecords;
    }

    public function __get($name) {
        if ($name === 'records') {
            $ultiRecords = $this->ultiRecords->getLimitedBy($this->ultiConfig->getNumberOfRecordsDisplayLimit());
            return $this->mapUltimaniaRecordsToLegacyArray($ultiRecords);
        }
    }

    /**
     * @param UltimaniaRecord[] $records
     */
    public function mapRecordsLoadedEventToLegacyEvent($records) {
        $this->aseco->releaseEvent(ULTI_EVENT_RECORDS_LOADED_LEGACY, $this->mapUltimaniaRecordsToLegacyArray($records));
    }

    /**
     * @param UltimaniaRecord $record
     */
    public function mapRecordEventToLegacyEvent($record) {
        $this->aseco->releaseEvent(ULTI_EVENT_RECORD_LEGACY, $this->mapUltimaniaRecordToLegacyArray($record));
    }

    /**
     * @param UltimaniaRecord $record
     * @return array
     */
    private function mapUltimaniaRecordToLegacyArray($record) {
        return [
            'login' => $record->getLogin(),
            'nick' => $record->getNick(),
            'score' => $record->getScore(),
        ];
    }

    /**
     * @param UltimaniaRecord[] $ultiRecords
     * @return array
     */
    private function mapUltimaniaRecordsToLegacyArray($ultiRecords) {
        return array_map('self::mapUltimaniaRecordToLegacyArray', $ultiRecords);
    }
}
