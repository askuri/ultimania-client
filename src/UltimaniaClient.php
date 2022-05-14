<?php

class UltimaniaClient {

    /** @var UltimaniaConfig  */
    private $config;

    /** @var UltimaniaDtoMapper */
    private $dtoMapper;

    /** @var string */
    private $ultimaniaApiServerUrl = 'http://ultimania5.askuri.de';

    /** @var string */
    private $adminRecManialinkUrl = 'ulti:admin_rec';

    /** @var string */
    private $viewReplayManialinkUrl = 'ulti:view_replay';

    /**
     * @param UltimaniaConfig $config
     * @param UltimaniaDtoMapper $dtoMapper
     */
    public function __construct($config, $dtoMapper) {
        $this->config = $config;
        $this->dtoMapper = $dtoMapper;
    }

    /**
     * @param string $mapUid
     * @param int|null $limit
     * @return UltimaniaRecord[]
     */
    public function getRecords($mapUid, $limit = null) {
        return $this->dtoMapper->mapRecordDtosToUltiRecords(
            $this->doRequest('GET',
                'maps/' . $mapUid . '/records',
                ['limit' => $limit]
            )['response']
        );
    }

    /**
     * @param string $recordId
     * @param string $replayContent
     * @return array{"replay_available": bool}
     */
    public function submitReplay($recordId, $replayContent) {
        return $this->doRequest(
            'POST',
            'records/' . $recordId . '/replay',
            $replayContent,
            true
        )['response'];
    }

    /**
     * @param UltimaniaRecord $record
     * @param string $mapUid
     * @return UltimaniaRecord|null return the record including its assigned ID or null if unsuccessful (e.g. player banned)
     */
    public function submitRecord($record, $mapUid) {
        $response = $this->doRequest(
            'PUT',
            'records',
            $this->dtoMapper->mapUltiRecordToRecordDto($record, $mapUid)
        )['response'];

        return $response == null ? null : $this->dtoMapper->mapRecordDtoToUltiRecord($response);
    }

    /**
     * @param string $playerLogin
     * @return UltimaniaPlayer
     */
    public function getPlayerInfo($playerLogin) {
        return $this->dtoMapper->mapPlayerDtoToUltiPlayer(
            $this->doRequest(
                'GET',
                'players/' . $playerLogin,
                []
            )['response']
        );
    }

    /**
     * @param UltimaniaPlayer $player
     * @return UltimaniaPlayer
     */
    public function registerOrUpdatePlayer($player) {
        return $this->dtoMapper->mapPlayerDtoToUltiPlayer(
            $this->doRequest(
                'PUT',
                'players',
                $this->dtoMapper->mapUltiPlayerToPlayerDto($player)
            )['response']
        );
    }

    /**
     * @param string $playerLogin
     * @return void
     */
    public function toggleAllowReplayDownlod($playerLogin) {
        $ultiPlayer = $this->getPlayerInfo($playerLogin);
        $ultiPlayer->setAllowReplayDownload( ! $ultiPlayer->isAllowReplayDownload());
        $this->registerOrUpdatePlayer($ultiPlayer);
    }

    /**
     * @param Challenge $map
     * @return void
     */
    public function registerOrUpdateMap($map) {
        $this->doRequest(
            'PUT',
            'maps',
            $this->dtoMapper->mapXasecoChallengeToMapDto($map)
        );
    }

    /**
     * @param string $method "GET"|"POST"|"PUT"
     * @param string $endpoint Path in URL after /api/v5/ (no leading slashes needed in this parameter)
     * @param mixed $payload When GET, these are query parameters, otherwise these are put as JSON in the body
     * @param bool $isBinaryRequest If it's binary, it won't be json_encoded. Instead, it goes straight to the body.
     * @return array{"response": mixed|null, "httpcode": int}
     */
    private function doRequest($method, $endpoint, $payload, $isBinaryRequest = false) {
        $handle = curl_init();

        $url = $this->ultimaniaApiServerUrl . '/api/v' . ULTI_API_VERSION . '/' . $endpoint;
        $httpHeaders = ['Accept: application/json'];

        curl_setopt($handle, CURLOPT_USERAGENT, 'TMF\Xaseco' . XASECO_VERSION . '\Ultimania' . ULTI_PLUGIN_VERSION . '\API' . ULTI_API_VERSION);
        curl_setopt($handle, CURLOPT_HEADER, false);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
        if ($method == 'GET') {
            $url .= http_build_query($payload);
        } else {
            if ($isBinaryRequest) {
                $httpHeaders[] = 'Content-Type: application/octet-stream';
            } else {
                $payload = json_encode($payload);
                $httpHeaders[] = 'Content-Type: application/json';
            }
            curl_setopt($handle, CURLOPT_POSTFIELDS, $payload);
        }
        curl_setopt($handle, CURLOPT_HTTPHEADER, $httpHeaders);
        curl_setopt($handle, CURLOPT_TIMEOUT, $this->config->getConnectTimeout());
        curl_setopt($handle, CURLOPT_LOW_SPEED_TIME, $this->config->getRequestTimeout());
        curl_setopt($handle, CURLOPT_URL, $url);

        $response = curl_exec($handle);

        $httpStatus = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);

        $curlErrorMessage = curl_error($handle);
        if (!empty($curlErrorMessage) && !($httpStatus >= 200 && $httpStatus <= 299)) {
            $curlErrorNumber = curl_errno($handle);
            trigger_error("[Ultimania] Error while communicating with Ultimania server. URL: $url; Parameters: ".print_r($payload, true)."; HTTP Status: $httpStatus; Curl error number: $curlErrorNumber; Message: $curlErrorMessage", E_USER_WARNING);
        }

        curl_close($handle);

        $jsonDecodedResponse = empty($response) ? null : json_decode($response, true);

        if ($jsonDecodedResponse !== null && !empty($jsonDecodedResponse['error'])) {
            trigger_error("[Ultimania] Received error from Ultimania server: " . print_r($jsonDecodedResponse['error'], true), E_USER_WARNING);
        }

        return [
            'response' => $jsonDecodedResponse,
            'httpcode' => $httpStatus,
        ];
    }

    /**
     * @param UltimaniaPlayer $player
     * @param string $uid
     * @return string
     */
    public function getLinkForAdminRecManialink(UltimaniaPlayer $player, $uid) {
        return $this->adminRecManialinkUrl. '?uid=' . urlencode($uid) . '&login=' . urlencode($player->getLogin());
    }

    /**
     *
     * @param UltimaniaRecord $record
     * @return string
     */
    public function getLinkForViewReplayManialink($record) {
        return $this->viewReplayManialinkUrl . '?record_id=' . urlencode($record->getId()) . '&amp;cache_bust=' . uniqid();
    }

    /**
     * @return false|string
     */
    public function fetchNewestAvailableUltimaniaClientVersion() {
        $url = $this->ultimaniaApiServerUrl . '/current_version.txt';
        $versionRaw = file_get_contents($url);

        if ($versionRaw === false) {
            trigger_error('[Ultimania] Unable to get current version information from ' . $url, E_USER_WARNING);
            return false;
        }
        return trim($versionRaw);
    }

    /**
     * Fetch the Ultimania plugin PHP script from the server.
     * @param string $version
     * @return false|string
     */
    public function fetchUpdate($version) {
        return file_get_contents($this->ultimaniaApiServerUrl . '/versions_repository/' . $version . '.php_');
    }

    /**
     * @return false|string
     */
    public function fetchInfotextInMainWindow() {
        $url = $this->ultimaniaApiServerUrl . '/main_window_info_text.txt';
        $info = file_get_contents($url);

        if ($info === false) {
            trigger_error('[Ultimania] Unable to get window infobox content from ' . $url, E_USER_WARNING);
            return false;
        }
        return $info;
    }
}
