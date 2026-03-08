<script lang="ts">
    import { Form, page } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import TextLink from '@/components/TextLink.svelte';
    import { Button } from '@/components/ui/button';
    import { Spinner } from '@/components/ui/spinner';
    import AuthLayout from '@/layouts/AuthLayout.svelte';
    import { logout } from '@/routes';
    import { send } from '@/routes/verification';

    const flashStatus = $derived($page.flash.status ?? '');
</script>

<AppHead title="Подтверждение email" />

<AuthLayout
    title="Подтвердите email"
    description="Подтвердите адрес email, перейдя по ссылке, которую мы только что отправили."
>
    {#if flashStatus === 'verification-link-sent'}
        <div class="mb-4 text-center text-sm font-medium text-green-600">
            На адрес email, указанный при регистрации, отправлена новая ссылка
            для подтверждения.
        </div>
    {/if}

    <Form {...send.form()} class="space-y-6 text-center">
        {#snippet children({ processing })}
            <Button type="submit" disabled={processing} variant="secondary">
                {#if processing}<Spinner />{/if}
                Отправить письмо повторно
            </Button>

            <TextLink href={logout()} as="button" class="mx-auto block text-sm">
                Выйти
            </TextLink>
        {/snippet}
    </Form>
</AuthLayout>
