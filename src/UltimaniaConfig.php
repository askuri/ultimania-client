<?php

class UltimaniaConfig {
    /** @var int */
    private $refresh_interval = 180;
    /** @var int */
    private $connect_timeout = 10;
    /** @var int */
    private $request_timeout = 10;
    /** @var int */
    private $number_of_records_display_limit = 25;

    /** @var SimpleXMLElement */
    private $displayRecordMessagesForBestOnly;
    /** @var SimpleXMLElement */
    private $messageRecordNew;
    /** @var SimpleXMLElement */
    private $messageRecordEqual;
    /** @var SimpleXMLElement */
    private $messageRecordNewRank;
    /** @var SimpleXMLElement */
    private $messageRecordFirst;

    /**
     * Creates an object of this calls and populates it with the values from the given xml file.
     * @param string $filename
     * @return UltimaniaConfig
     */
    public static function instantiateFromFile($filename) {
        $ultiConfig = new self();
        $rawConfig = simplexml_load_file($filename);
        $ultiConfig->displayRecordMessagesForBestOnly = $rawConfig->display_record_messages_for_best_only;
        $ultiConfig->messageRecordNew = $rawConfig->messages->record_new;
        $ultiConfig->messageRecordEqual = $rawConfig->messages->record_equal;
        $ultiConfig->messageRecordNewRank = $rawConfig->messages->record_new_rank;
        $ultiConfig->messageRecordFirst = $rawConfig->messages->record_first;

        return $ultiConfig;
    }

    /**
     * @return int
     */
    public function getRefreshInterval() {
        return $this->refresh_interval;
    }

    /**
     * @return int
     */
    public function getConnectTimeout() {
        return $this->connect_timeout;
    }

    /**
     * @return int
     */
    public function getRequestTimeout() {
        return $this->request_timeout;
    }

    /**
     * @return int
     */
    public function getNumberOfRecordsDisplayLimit() {
        return $this->number_of_records_display_limit;
    }

    /**
     * @return bool
     */
    public function getDisplayRecordMessagesForBestOnly() {
        return $this->xmlContentToBool($this->displayRecordMessagesForBestOnly);
    }

    /**
     * @return string
     */
    public function getMessageRecordNew() {
        return (string) $this->messageRecordNew;
    }

    /**
     * @return string
     */
    public function getMessageRecordEqual() {
        return (string) $this->messageRecordEqual;
    }

    /**
     * @return string
     */
    function getMessageRecordNewRank() {
        return (string) $this->messageRecordNewRank;
    }

    /**
     * @return string
     */
    public function getMessageRecordFirst() {
        return (string) $this->messageRecordFirst;
    }

    /**
     * @param SimpleXMLElement $val
     * @return bool
     */
    private function xmlContentToBool($val) {
        return filter_var((string) $val, FILTER_VALIDATE_BOOLEAN);
    }


}
