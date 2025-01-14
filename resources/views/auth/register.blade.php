<x-global-layout>
    <div class="bg-body-tertiary">
        <div class="container py-3">
            <h2 class="text-body-emphasis">Регистрация</h2>

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('register') }}">
                @csrf

                <!-- Name -->
                <div class="mb-3">
                    <x-input-label for="name" :value="__('ФИО')" />
                    <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <!-- Phone -->
                <div class="mb-3">
                    <x-input-label for="phone" :value="__('Телефон')" />
                    <x-text-input id="phone" class="block mt-1 w-full" placeholder="+79991112233" type="text" name="phone" :value="old('phone')" required autofocus autocomplete="phone" />
                    <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                </div>

                <!-- Email Address -->
                <div class="mb-3">
                    <x-input-label for="email" :value="__('Почта')" />
                    <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div class="mb-3">
                    <x-input-label for="role_id" :value="__('Тип записи')" />
                    <select class="form-select" id="role_id" name="user_role_id" required>
                        <option value="">Выберите...</option>
                        @foreach ($userRoleList as $userRole)
                            <option value="{{ $userRole['id'] }}" {{ (collect(old('user_role_id', $request['user_role_id']))->contains($userRole['id'])) ? 'selected':'' }}>{{ $userRole['value'] }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('role_id')" class="mt-2" />
                </div>

                <!-- Password -->
                <div class="mb-3">
                    <x-input-label for="password" :value="__('Пароль')" />
                    <x-text-input id="password" class="block mt-1 w-full"
                                    type="password"
                                    name="password"
                                    required autocomplete="new-password" />

                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <!-- Confirm Password -->
                <div class="mb-3">
                    <x-input-label for="password_confirmation" :value="__('Подтвердите пароль')" />

                    <x-text-input id="password_confirmation" class="block mt-1 w-full"
                                    type="password"
                                    name="password_confirmation" required autocomplete="new-password" />

                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                </div>

                <div class="flex items-center justify-end mt-4">
                    <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                        {{ __('Уже есть аккаунт?') }}
                    </a>

                    <x-primary-button class="ms-4">
                        {{ __('Регистрация') }}
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-global-layout>
