<?php

class UltimaniaConfig {
    private $refresh_interval = 180;
    private $connect_timeout = 10;
    private $request_timeout = 10;
    private $number_of_records_display_limit = 25;
    private $displayRecordMessagesForBestOnly;

    private $messageRecordNew;
    private $messageRecordEqual;
    private $messageRecordNewRank;
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

    public function getRefreshInterval() {
        return $this->refresh_interval;
    }

    public function getConnectTimeout() {
        return $this->connect_timeout;
    }

    public function getRequestTimeout() {
        return $this->request_timeout;
    }

    public function getNumberOfRecordsDisplayLimit() {
        return $this->number_of_records_display_limit;
    }

    /**
     * @return bool
     */
    public function getDisplayRecordMessagesForBestOnly() {
        return $this->xmlContentToBool($this->displayRecordMessagesForBestOnly);
    }

    public function getMessageRecordNew() {
        return (string) $this->messageRecordNew;
    }

    public function getMessageRecordEqual() {
        return (string) $this->messageRecordEqual;
    }

    function getMessageRecordNewRank() {
        return (string) $this->messageRecordNewRank;
    }

    public function getMessageRecordFirst() {
        return (string) $this->messageRecordFirst;
    }

    /**
     * @return bool
     */
    private function xmlContentToBool($val) {
        return filter_var((string) $val, FILTER_VALIDATE_BOOLEAN);
    }


}
