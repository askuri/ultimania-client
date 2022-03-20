<?php

class UltimaniaDtoMapper {

    /**
     * @param array{'player': array{"login": string, "nick": "string"}, "nick": string, "score": int, "updated_at": int}[]|null $dtos
     * @return UltimaniaRecord[]
     */
    public function mapRecordDtosToUltiRecords($dtos) {
        if (empty($dtos)) {
            return [];
        }
        return array_map('self::mapRecordDtoWithPlayerDtoToUltiRecord', $dtos);
    }

    /**
     * @param array{'player': array{"login": string, "nick": "string", "allow_replay_download": bool, "banned": bool}, "id": string|null, "map_uid": string, "score": int, "updated_at": int} $dto
     * @return UltimaniaRecord
     */
    public function mapRecordDtoWithPlayerDtoToUltiRecord($dto) {
        return new UltimaniaRecord(
            $this->mapPlayerDtoToUltimaniaPlayer($dto['player']),
            $dto['map_uid'],
            $dto['score'],
            $dto['updated_at'],
            $dto['id']
        );
    }

    /**
     * @param array{'player_login': string, 'map_uid': string, "id": string|null, "score": int, "updated_at": int} $dto
     * @return UltimaniaRecord
     */
    public function mapRecordDtoToUltiRecord($dto) {
        return new UltimaniaRecord(
            $dto['player_login'],
            $dto['map_uid'],
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
    public function mapUltiRecordToRecordDto(UltimaniaRecord $ultimaniaRecord, $mapUid) {
        return [
            'player_login' => $ultimaniaRecord->getPlayer()->getLogin(),
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
     * @param array{'login': string, 'nick': string, 'allow_replay_download': bool, 'banned': bool} $playerDto
     * @return UltimaniaPlayer
     */
    public function mapPlayerDtoToUltimaniaPlayer($playerDto) {
        return new UltimaniaPlayer(
            $playerDto['login'],
            $playerDto['nick'],
            $playerDto['allow_replay_download'],
            $playerDto['banned']
        );
    }
}
