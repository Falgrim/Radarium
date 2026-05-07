<?php

namespace App\Http\Requests;

use App\Enum\ModerationAlertTableNameEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ModerationAlertPostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (!Auth::user()) {
            return false;
        }
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => [Rule::enum(ModerationAlertTableNameEnum::class)],
            'row_id' => ['required', 'integer', 'min:1', 'max:1000000'],
            'api_channel_post_id' => ['nullable', 'integer', 'exists:api_channel_posts,id'],
            'description' => ['nullable', 'string', 'min:5', 'max:255'],
        ];
    }
}
