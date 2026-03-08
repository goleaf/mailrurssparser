<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import {
        InputOTP,
        InputOTPGroup,
        InputOTPSlot,
    } from '@/components/ui/input-otp';
    import AuthLayout from '@/layouts/AuthLayout.svelte';
    import { store } from '@/routes/two-factor/login';
    import type { TwoFactorConfigContent } from '@/types';

    let showRecoveryInput = $state(false);
    let code = $state('');

    const authConfigContent: TwoFactorConfigContent = $derived.by(() => {
        if (showRecoveryInput) {
            return {
                title: 'Резервный код',
                description:
                    'Подтвердите доступ к аккаунту, введя один из резервных кодов восстановления.',
                buttonText: 'войти с кодом приложения',
            };
        }

        return {
            title: 'Код приложения',
            description:
                'Введите код, который показывает приложение-аутентификатор.',
            buttonText: 'войти по резервному коду',
        };
    });

    function toggleRecoveryMode(clearErrors: () => void) {
        showRecoveryInput = !showRecoveryInput;
        clearErrors();
        code = '';
    }
</script>

<AppHead title="Двухфакторная аутентификация" />

<AuthLayout
    title={authConfigContent.title}
    description={authConfigContent.description}
>
    <div class="space-y-6">
        {#if !showRecoveryInput}
            <Form
                {...store.form()}
                class="space-y-4"
                resetOnError
                onError={() => (code = '')}
            >
                {#snippet children({ errors, processing, clearErrors })}
                    <input type="hidden" name="code" value={code} />
                    <div
                        class="flex flex-col items-center justify-center space-y-3 text-center"
                    >
                        <div class="flex w-full items-center justify-center">
                            <InputOTP
                                id="otp"
                                bind:value={code}
                                maxlength={6}
                                disabled={processing}
                            >
                                <InputOTPGroup>
                                    {#each { length: 6 } as _, i (i)}
                                        <InputOTPSlot index={i} />
                                    {/each}
                                </InputOTPGroup>
                            </InputOTP>
                        </div>
                        <InputError message={errors.code} />
                    </div>
                    <Button type="submit" class="w-full" disabled={processing}
                        >Продолжить</Button
                    >
                    <div class="text-center text-sm text-muted-foreground">
                        <span>или можно </span>
                        <button
                            type="button"
                            class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            onclick={() => toggleRecoveryMode(clearErrors)}
                        >
                            {authConfigContent.buttonText}
                        </button>
                    </div>
                {/snippet}
            </Form>
        {:else}
            <Form {...store.form()} class="space-y-4" resetOnError>
                {#snippet children({ errors, processing, clearErrors })}
                    <Input
                        name="recovery_code"
                        type="text"
                        placeholder="Введите резервный код"
                        required
                    />
                    <InputError message={errors.recovery_code} />
                    <Button type="submit" class="w-full" disabled={processing}
                        >Продолжить</Button
                    >

                    <div class="text-center text-sm text-muted-foreground">
                        <span>или можно </span>
                        <button
                            type="button"
                            class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            onclick={() => toggleRecoveryMode(clearErrors)}
                        >
                            {authConfigContent.buttonText}
                        </button>
                    </div>
                {/snippet}
            </Form>
        {/if}
    </div>
</AuthLayout>
