<script lang="ts">
    import { Form, page } from '@inertiajs/svelte';
    import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
    import AppHead from '@/components/AppHead.svelte';
    import DeleteUser from '@/components/DeleteUser.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import TextLink from '@/components/TextLink.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import SettingsLayout from '@/layouts/settings/Layout.svelte';
    import { edit } from '@/routes/profile';
    import { send } from '@/routes/verification';
    import type { BreadcrumbItem } from '@/types';

    let {
        mustVerifyEmail,
    }: {
        mustVerifyEmail: boolean;
    } = $props();

    const breadcrumbItems: BreadcrumbItem[] = [
        {
            title: 'Настройки профиля',
            href: edit(),
        },
    ];

    const user = $derived($page.props.auth.user);
    const flashStatus = $derived($page.flash.status ?? '');
</script>

<AppHead title="Настройки профиля" />

<AppLayout breadcrumbs={breadcrumbItems}>
    <h1 class="sr-only">Настройки профиля</h1>

    <SettingsLayout>
        <div class="flex flex-col space-y-6">
            <Heading
                variant="small"
                title="Данные профиля"
                description="Обновите имя и адрес электронной почты"
            />

            <Form
                {...ProfileController.update.form()}
                class="space-y-6"
                options={{ preserveScroll: true }}
            >
                {#snippet children({ errors, processing })}
                    <div class="grid gap-2">
                        <Label for="name">Имя</Label>
                        <Input
                            id="name"
                            name="name"
                            class="mt-1 block w-full"
                            value={user.name}
                            required
                            autocomplete="name"
                            placeholder="Полное имя"
                        />
                        <InputError class="mt-2" message={errors.name} />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Электронная почта</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            class="mt-1 block w-full"
                            value={user.email}
                            required
                            autocomplete="username"
                            placeholder="Электронная почта"
                        />
                        <InputError class="mt-2" message={errors.email} />
                    </div>

                    {#if mustVerifyEmail && !user.email_verified_at}
                        <div>
                            <p class="-mt-4 text-sm text-muted-foreground">
                                Ваш адрес электронной почты не подтверждён.
                                <TextLink href={send()} as="button">
                                    Нажмите, чтобы отправить письмо повторно.
                                </TextLink>
                            </p>

                            {#if flashStatus === 'verification-link-sent'}
                                <div
                                    class="mt-2 text-sm font-medium text-green-600"
                                >
                                    На ваш адрес электронной почты отправлена
                                    новая ссылка для подтверждения.
                                </div>
                            {/if}
                        </div>
                    {/if}

                    <div class="flex items-center gap-4">
                        <Button
                            type="submit"
                            disabled={processing}
                            data-test="update-profile-button">Сохранить</Button
                        >
                    </div>
                {/snippet}
            </Form>
        </div>

        <DeleteUser />
    </SettingsLayout>
</AppLayout>
