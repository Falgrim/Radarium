<?php

namespace App\Http\Controllers;

use App\Enum\ModerationAlertStatusEnum;
use App\Enum\ModerationAlertSystemEnum;
use App\Http\Requests\ModerationAlertPostRequest;
use App\Models\ModerationAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ModerationAlertController extends Controller
{
    public function store(ModerationAlertPostRequest $request): JsonResponse
    {
        $data = $request->validated();

        $author = ModerationAlert::where('user_id', Auth::user()->id)
            ->where('table_name', $data['type'])
            ->where('table_row_id', $data['row_id'])
            ->first();

        if ($author) {
            return response()->json([
                'code' => 403,
                'message' => 'Вы уже оставляли обращение по данной записи.'
            ], 403);
        }

        ModerationAlert::create([
            'user_id' => Auth::user()->id,
            'is_system' => ModerationAlertSystemEnum::User,
            'table_name' => $data['type'],
            'table_row_id' => $data['row_id'],
            'description' => $data['description'] ?? null,
            'status' => ModerationAlertStatusEnum::New,
        ]);

        return response()->json([
            'code' => 200,
            'message' => 'Ващ запрос успешно отправлен.'
        ], 200);
    }
}
