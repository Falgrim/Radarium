<?php

namespace App\Http\Controllers;

use App\Enum\ModerationAlertStatusEnum;
use App\Enum\ModerationAlertSystemEnum;
use App\Enum\ModerationAlertTableNameEnum;
use App\Http\Requests\ModerationAlertPostRequest;
use App\Models\ApiChannelPost;
use App\Models\Builder;
use App\Models\CompanyJob;
use App\Models\ModerationAlert;
use App\Models\Specialist;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ModerationAlertController extends Controller
{
    public function store(ModerationAlertPostRequest $request): JsonResponse
    {
        $data = $request->validated();

        /** @var ModerationAlertTableNameEnum $tableName */
        $tableName = $data['type'];

        $rowId = (int) $data['row_id'];

        $apiChannelPostId = isset($data['api_channel_post_id']) ? (int) $data['api_channel_post_id'] : null;

        if ($apiChannelPostId === null) {
            $apiChannelPostId = match ($tableName) {
                ModerationAlertTableNameEnum::Builder => Builder::query()->whereKey($rowId)->value('api_channel_post_id'),
                ModerationAlertTableNameEnum::Specialist => Specialist::query()->whereKey($rowId)->value('api_channel_post_id'),
                ModerationAlertTableNameEnum::CompanyJob => CompanyJob::query()->whereKey($rowId)->value('api_channel_post_id'),
                default => null,
            };
        } elseif ($tableName === ModerationAlertTableNameEnum::Author) {
            $post = ApiChannelPost::query()->find($apiChannelPostId);
            if ($post === null || (int) $post->api_post_user_id !== $rowId) {
                return response()->json([
                    'code' => 422,
                    'message' => 'Указанное сообщение не принадлежит этому автору.',
                ], 422);
            }
        }

        $hasOpenUserAlert = ModerationAlert::query()
            ->where('user_id', Auth::user()->id)
            ->where('is_system', ModerationAlertSystemEnum::User)
            ->where('table_name', $tableName)
            ->where('table_row_id', $rowId)
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
            'table_row_id' => $rowId,
            'api_channel_post_id' => $apiChannelPostId,
            'description' => $data['description'] ?? null,
            'status' => ModerationAlertStatusEnum::New,
        ]);

        return response()->json([
            'code' => 200,
            'message' => 'Ваш запрос успешно отправлен.'
        ], 200);
    }
}
