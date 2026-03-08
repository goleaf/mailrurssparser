<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import SettingsLayout from '@/layouts/settings/Layout.svelte';
    import { edit } from '@/routes/user-password';
    import type { BreadcrumbItem } from '@/types';

    const breadcrumbItems: BreadcrumbItem[] = [
        {
            title: 'Настройки пароля',
            href: edit(),
        },
    ];
</script>

<AppHead title="Настройки пароля" />

<AppLayout breadcrumbs={breadcrumbItems}>
    <h1 class="sr-only">Настройки пароля</h1>

    <SettingsLayout>
        <div class="space-y-6">
            <Heading
                variant="small"
                title="Обновить пароль"
                description="Используйте длинный и случайный пароль, чтобы защитить аккаунт"
            />

            <Form
                {...PasswordController.update.form()}
                class="space-y-6"
                options={{ preserveScroll: true }}
                resetOnSuccess
                resetOnError={[
                    'password',
                    'password_confirmation',
                    'current_password',
                ]}
            >
                {#snippet children({ errors, processing })}
                    <div class="grid gap-2">
                        <Label for="current_password">Текущий пароль</Label>
                        <Input
                            id="current_password"
                            name="current_password"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="current-password"
                            placeholder="Текущий пароль"
                        />
                        <InputError message={errors.current_password} />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password">Новый пароль</Label>
                        <Input
                            id="password"
                            name="password"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                            placeholder="Новый пароль"
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation"
                            >Подтвердите пароль</Label
                        >
                        <Input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                            placeholder="Подтвердите пароль"
                        />
                        <InputError message={errors.password_confirmation} />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button
                            type="submit"
                            disabled={processing}
                            data-test="update-password-button"
                        >
                            Сохранить пароль
                        </Button>
                    </div>
                {/snippet}
            </Form>
        </div>
    </SettingsLayout>
</AppLayout>
