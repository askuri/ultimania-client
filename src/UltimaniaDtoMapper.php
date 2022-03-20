<?php

class UltimaniaDtoMapper {

    /**
     * @param array{'player': array{"login": string, "nick": "string"}, "nick": string, "score": int, "updated_at": int}[]|null $dtos
     * @return UltimaniaRecord[]
     */
    public function mapApiRecordDtosToUltiRecords($dtos) {
        if (empty($dtos)) {
            return [];
        }
        return array_map('self::mapApiRecordWithPlayerDtoToUltiRecord', $dtos);
    }

    /**
     * @param array{'player': array{"login": string, "nick": "string"}, "id": string|null, "score": int, "updated_at": int} $dto
     * @return UltimaniaRecord
     */
    public function mapApiRecordWithPlayerDtoToUltiRecord($dto) {
        return new UltimaniaRecord(
            $dto['player']['login'],
            $dto['player']['nick'],
            $dto['score'],
            $dto['updated_at'],
            $dto['id']
        );
    }

    /**
     * @param array{'player_login': string, "id": string|null, "score": int, "updated_at": int} $dto
     * @return UltimaniaRecord
     */
    public function mapApiRecordDtoToUltiRecord($dto) {
        return new UltimaniaRecord(
            $dto['player_login'],
            'remove me', // todo remove
            $dto['score'],
            $dto['updated_at'],
            $dto['id']
        );
    }

    /**
     * @param UltimaniaRecord $ultimaniaRecord
     * @param string $mapUid
     * @return array{'player_login': string, "map_uid": string, "score": int}
     */
    public function mapUltiRecordToApiRecordDto(UltimaniaRecord $ultimaniaRecord, $mapUid) {
        return [
            'player_login' => $ultimaniaRecord->getLogin(),
            'map_uid' => $mapUid,
            'score' => $ultimaniaRecord->getScore()
        ];
    }

    /**
     * @param Challenge $map
     * @return array{'uid': string, 'name': string}
     */
    public function mapXasecoChallengeToMapDto(Challenge $map) {
        return [
            'uid' => $map->uid,
            'name' => $map->name,
        ];
    }

    /**
     * @param Player $player
     * @return array{'login': string, 'nick': string}
     */
    public function mapXasecoPlayerToPlayerDto(Player $player) {
        return [
            'login' => $player->login,
            'nick' => $player->nickname,
        ];
    }

    /**
     * @param array{'login': string, 'nick': string, 'banned': bool} $playerDto
     * @return UltimaniaPlayer
     */
    public function mapPlayerDtoToUltimaniaPlayer($playerDto) {
        return new UltimaniaPlayer(
            $playerDto['login'],
            $playerDto['nick'],
            $playerDto['banned']
        );
    }
}
