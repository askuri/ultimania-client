<?php

/**
 * @todo keep the xml file? do we need it?
 */
class UltimaniaConfig {
    /** @var int */
    private $refresh_interval = 180;
    /** @var int */
    private $connect_timeout = 10;
    /** @var int */
    private $request_timeout = 10;
    /** @var int */
    private $number_of_records_display_limit = 25;

    /** @var string|null */
    private $messageRecordNew;
    /** @var string|null */
    private $messageRecordEqual;
    /** @var string|null */
    private $messageRecordNewRank;
    /** @var string|null */
    private $messageRecordFirst;

    /**
     * Creates an object of this calls and populates it with the values from the given xml file.
     * It's not necessary to instantiate this class with a config file. Just create it with the
     * usual constructor and it will take the defaults.
     *
     * @param string $filename
     * @return UltimaniaConfig
     */
    public static function instantiateFromFile($filename) {
        $ultiConfig = new self();

        if (file_exists($filename)) {
            $rawConfig = simplexml_load_file($filename);
            $ultiConfig->messageRecordNew = (string)$rawConfig->messages->record_new;
            $ultiConfig->messageRecordEqual = (string)$rawConfig->messages->record_equal;
            $ultiConfig->messageRecordNewRank = (string)$rawConfig->messages->record_new_rank;
            $ultiConfig->messageRecordFirst = (string)$rawConfig->messages->record_first;
        }

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
     * @return string
     */
    public function getMessageRecordNew() {
        return isset($this->messageRecordNew) ? $this->messageRecordNew
            : '{#server}>> {#highlite}{1}{#record} secured his/her {#rank}{2}{#record}. Ultimania Record! Score: {#highlite}{3}{#record} $n({#rank}{4}{#highlite}+{5}{#record})';
    }

    /**
     * @return string
     */
    public function getMessageRecordEqual() {
        return isset($this->messageRecordEqual) ? $this->messageRecordEqual
            : '{#server}>> {#highlite}{1}{#record} equaled his/her {#rank}{2}{#record}. Ultimania Record! Score: {#highlite}{3}';
    }

    /**
     * @return string
     */
    function getMessageRecordNewRank() {
        return isset($this->messageRecordNewRank) ? $this->messageRecordNewRank
            : '{#server}>> {#highlite}{1}{#record} gained the {#rank}{2}{#record}. Ultimania Record! Score: {#highlite}{3}{#record} $n({#rank}{4}{#highlite}+{5}{#record})';
    }

    /**
     * @return string
     */
    public function getMessageRecordFirst() {
        return isset($this->messageRecordFirst) ? $this->messageRecordFirst
            : '{#server}>> {#highlite}{1}{#record} claimed the {#rank}{2}{#record}. Ultimania Record! Score: {#highlite}{3}';
    }
}
