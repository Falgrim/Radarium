<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;
use Propaganistas\LaravelPhone\PhoneNumber;

class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Введите ваше ФИО',
            'name.string' => 'Неверный формат ФИО',
            'name.max' => 'ФИО не может быть больше 255 символов',

            'phone.required' => 'Введите номер мобильного телефон',
            'phone.phone' => 'Введите номер телефона формата РФ',
            'phone.unique' => 'Данный номер телефона уже зарегистрирован в системе',

            'email.required' => 'Введите ваш Email',
            'email.string' => 'Неверный формат Email',
            'email.email' => 'Неверный формат Email',
            'email.max' => 'Email не может быть больше 255 символов',
            'email.unique' => 'Данный Email уже зарегистрирован в системе',

            'password.required' => 'Введите пароль',
            'password.confirmed' => 'Введенные пароли не совпадают',
        ];
    }

    /**
     * Подготовить данные для валидации.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => (string) new PhoneNumber($this->phone, 'RU'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'phone:mobile,RU', 'unique:'.User::class.',phone'],
            'user_role_id' => ['exists:App\Models\UserRole,id'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];
    }
}
