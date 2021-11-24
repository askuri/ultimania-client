<?php

class UltimaniaConfig {
    private $refresh_interval = 180;
    private $connect_timeout = 10;
    private $request_timeout = 10;
    private $number_of_records_display_limit = 25;

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
}
