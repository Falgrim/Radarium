<?php

namespace App\Http\Controllers;

use App\Enum\ModerationAlertStatusEnum;
use App\Enum\ModerationAlertSystemEnum;
use App\Enum\ModerationAlertTableNameEnum;
use App\Http\Requests\ModerationAlertPostRequest;
use App\Models\ModerationAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ModerationAlertController extends Controller
{
    public function store(ModerationAlertPostRequest $request): JsonResponse
    {
        $data = $request->validated();

        /** @var ModerationAlertTableNameEnum $tableName */
        $tableName = $data['type'];

        $hasOpenUserAlert = ModerationAlert::query()
            ->where('user_id', Auth::user()->id)
            ->where('is_system', ModerationAlertSystemEnum::User)
            ->where('table_name', $tableName)
            ->where('table_row_id', $data['row_id'])
            ->where('status', ModerationAlertStatusEnum::New)
            ->exists();

        if ($hasOpenUserAlert) {
            return response()->json([
                'code' => 403,
                'message' => 'У вас уже есть открытое обращение по этой записи. Дождитесь его рассмотрения или обратитесь в поддержку.',
            ], 403);
        }

        ModerationAlert::create([
            'user_id' => Auth::user()->id,
            'is_system' => ModerationAlertSystemEnum::User,
            'table_name' => $tableName,
            'table_row_id' => $data['row_id'],
            'description' => $data['description'] ?? null,
            'status' => ModerationAlertStatusEnum::New,
        ]);

        return response()->json([
            'code' => 200,
            'message' => 'Ваш запрос успешно отправлен.'
        ], 200);
    }
}
