<?php

class UltimaniaClient {

    /** @var UltimaniaConfig  */
    private $config;

    /** @var UltimaniaDtoMapper */
    private $dtoMapper;

    /** @var string  @todo set correct url*/
    private $apiUrl = 'http://localhost:8000/api';

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
        return $this->dtoMapper->mapApiRecordDtosToUltiRecords(
            $this->doRequest('GET',
                'maps/' . $mapUid . '/records', ['limit' => $limit]
            )['response']
        );
    }

    public function getReplay($recordId) {

    }

    public function submitReplay($recordId, $replayContent) {

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
            $this->dtoMapper->mapUltiRecordToApiRecordDto($record, $mapUid)
        )['response'];

        return $response == null ? null : $this->dtoMapper->mapApiRecordDtoToUltiRecord($response);
    }

    /**
     * @param Player $player
     * @return UltimaniaPlayer
     */
    public function registerPlayer($player) {
        return $this->dtoMapper->mapPlayerDtoToUltimaniaPlayer(
            $this->doRequest(
                'PUT',
                'players',
                $this->dtoMapper->mapXasecoPlayerToPlayerDto($player)
            )['response']
        );
    }

    /**
     * @param Challenge $map
     * @return void
     */
    public function registerMap($map) {
        $this->doRequest(
            'PUT',
            'maps',
            $this->dtoMapper->mapXasecoChallengeToMapDto($map)
        );
    }

    /**
     * @param string $method "GET"|"POST"|"PUT"
     * @param string $endpoint Path in URL after /api/v5/ (no leading slashes needed in this parameter)
     * @param mixed[] $parameters When GET, these are query parameters, otherwise these are put as JSON in the body
     * @return array{"response": mixed|null, "httpcode": int}
     */
    private function doRequest($method, $endpoint, array $parameters = []) {
        $handle = curl_init();

        $url = $this->apiUrl . '/v' . ULTI_API_VERSION . '/' . $endpoint;

        curl_setopt($handle, CURLOPT_USERAGENT, 'TMF\Xaseco' . XASECO_VERSION . '\Ultimania' . ULTI_VERSION . '\API' . ULTI_API_VERSION);
        curl_setopt($handle, CURLOPT_HEADER, false);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
        if ($method == 'GET') {
            $url .= http_build_query($parameters);
        } else {
            curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
        }
        curl_setopt($handle, CURLOPT_TIMEOUT, $this->config->getConnectTimeout());
        curl_setopt($handle, CURLOPT_LOW_SPEED_TIME, $this->config->getRequestTimeout());
        curl_setopt($handle, CURLOPT_URL, $url);

        $response = curl_exec($handle);

        $httpStatus = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);

        $curlErrorMessage = curl_error($handle);
        if (!empty($curlErrorMessage) && !($httpStatus >= 200 && $httpStatus <= 299)) {
            $curlErrorNumber = curl_errno($handle);
            trigger_error("[Ultimania] Error while communicating with Ultimania server. URL: $url; Parameters: ".print_r($parameters, true)."; HTTP Status: $httpStatus; Curl error number: $curlErrorNumber; Message: $curlErrorMessage", E_USER_WARNING);
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
}