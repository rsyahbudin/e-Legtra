<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('')" :description="__('Enter your NIK and password below to log in')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- NIK -->
            <flux:input
                name="nik"
                :label="__('NIK')"
                :value="old('nik')"
                type="text"
                required
                autofocus
                autocomplete="username"
                placeholder="Masukkan NIK"
            />



            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Log in') }}
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts.auth>
