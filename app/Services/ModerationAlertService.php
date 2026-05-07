<?php

namespace App\Services;

use App\Enum\ModerationAlertStatusEnum;
use App\Enum\ModerationAlertSystemEnum;
use App\Enum\ModerationAlertTableNameEnum;
use App\Models\ModerationAlert;

class ModerationAlertService
{
    public function __construct() {}

    public function createAlert(
        int $userId,
        ModerationAlertSystemEnum $isSystem,
        ModerationAlertTableNameEnum $tableName,
        int $tableRowId,
        ?string $description,
        ?int $apiChannelPostId = null,
    ) {
        ModerationAlert::create([
            'user_id' => $userId,
            'is_system' => $isSystem,
            'table_name' => $tableName,
            'table_row_id' => $tableRowId,
            'api_channel_post_id' => $apiChannelPostId,
            'description' => $description,
            'status' => ModerationAlertStatusEnum::New,
        ]);
    }
}
