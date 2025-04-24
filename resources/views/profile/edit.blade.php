<x-global-layout>
    <div class="my-5">
        <div class="p-5 bg-body-tertiary">
            <div class="container py-0">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>
    </div>

    <div class="my-5">
        <div class="p-5 bg-body-tertiary">
            <div class="container py-0">
                @include('profile.partials.update-password-form')
            </div>
        </div>
    </div>
</x-global-layout>
