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
        return array_map('self::mapRecordDtoWithPlayerDtoToUltiRecord', $dtos); /* @phpstan-ignore-line PHPStand doesn't understand this string is a callback. */
    }

    /**
     * @param array{'player': array{"login": string, "nick": "string", "allow_replay_download": bool, "banned": bool}, "id": string|null, "map_uid": string, "score": int, "updated_at": int, "replay_available": bool} $dto
     * @return UltimaniaRecord
     */
    public function mapRecordDtoWithPlayerDtoToUltiRecord($dto) {
        return new UltimaniaRecord(
            $this->mapPlayerDtoToUltiPlayer($dto['player']),
            $dto['map_uid'],
            $dto['score'],
            $dto['updated_at'],
            $dto['id'],
            $dto['replay_available']
        );
    }

    /**
     * @param array{'player_login': string, 'map_uid': string, "id": string|null, "score": int, "updated_at": int, "replay_available": bool} $dto
     * @return UltimaniaRecord
     */
    public function mapRecordDtoToUltiRecord($dto) {
        return new UltimaniaRecord(
            $dto['player_login'],
            $dto['map_uid'],
            $dto['score'],
            $dto['updated_at'],
            $dto['id'],
            $dto['replay_available']
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
     * @param array{'login': string, 'nick': string, 'allow_replay_download': bool, 'banned': bool}|null $playerDto
     * @return UltimaniaPlayer|null
     */
    public function mapPlayerDtoToUltiPlayer($playerDto) {
        if (empty($playerDto)) {
            return null;
        }
        return new UltimaniaPlayer(
            $playerDto['login'],
            $playerDto['nick'],
            $playerDto['allow_replay_download'],
            $playerDto['banned']
        );
    }

    /**
     * @param UltimaniaPlayer $player
     * @return array{'login': string, 'nick': string, 'is_allow_replay_download'?: bool}
     */
    public function mapUltiPlayerToPlayerDto(UltimaniaPlayer $player) {
        $dto = [
            'login' => $player->getLogin(),
            'nick' => $player->getNick(),
        ];
        if ($player->isAllowReplayDownload() !== null) {
            $dto['allow_replay_download'] = $player->isAllowReplayDownload();
        }

        return $dto;
    }
}
