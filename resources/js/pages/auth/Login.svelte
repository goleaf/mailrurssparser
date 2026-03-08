<script lang="ts">
    import { Form, page } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import TextLink from '@/components/TextLink.svelte';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import AuthBase from '@/layouts/AuthLayout.svelte';
    import { register } from '@/routes';
    import { store } from '@/routes/login';
    import { request } from '@/routes/password';

    let {
        canResetPassword,
        canRegister,
    }: {
        canResetPassword: boolean;
        canRegister: boolean;
    } = $props();

    const flashStatus = $derived($page.flash.status ?? '');
</script>

<AppHead title="Вход" />

<AuthBase
    title="Войдите в аккаунт"
    description="Введите email и пароль, чтобы войти"
>
    {#if flashStatus}
        <div class="mb-4 text-center text-sm font-medium text-green-600">
            {flashStatus}
        </div>
    {/if}

    <Form
        {...store.form()}
        resetOnSuccess={['password']}
        class="flex flex-col gap-6"
    >
        {#snippet children({ errors, processing })}
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Электронная почта</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autocomplete="email"
                        placeholder="email@example.com"
                    />
                    <InputError message={errors.email} />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">Пароль</Label>
                        {#if canResetPassword}
                            <TextLink href={request()} class="text-sm">
                                Забыли пароль?
                            </TextLink>
                        {/if}
                    </div>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="Пароль"
                    />
                    <InputError message={errors.password} />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" name="remember" />
                        <span>Запомнить меня</span>
                    </Label>
                </div>

                <Button
                    type="submit"
                    class="mt-4 w-full"
                    disabled={processing}
                    data-test="login-button"
                >
                    {#if processing}<Spinner />{/if}
                    Войти
                </Button>
            </div>

            {#if canRegister}
                <div class="text-center text-sm text-muted-foreground">
                    Нет аккаунта?
                    <TextLink href={register()}>Регистрация</TextLink>
                </div>
            {/if}
        {/snippet}
    </Form>
</AuthBase>
