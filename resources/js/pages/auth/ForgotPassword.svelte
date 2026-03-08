<script lang="ts">
    import { Form, page } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import TextLink from '@/components/TextLink.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import AuthLayout from '@/layouts/AuthLayout.svelte';
    import { login } from '@/routes';
    import { email } from '@/routes/password';

    const flashStatus = $derived($page.flash.status ?? '');
</script>

<AppHead title="Забыли пароль" />

<AuthLayout
    title="Забыли пароль"
    description="Введите email, чтобы получить ссылку для сброса пароля"
>
    {#if flashStatus}
        <div class="mb-4 text-center text-sm font-medium text-green-600">
            {flashStatus}
        </div>
    {/if}

    <div class="space-y-6">
        <Form {...email.form()}>
            {#snippet children({ errors, processing })}
                <div class="grid gap-2">
                    <Label for="email">Электронная почта</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        autocomplete="off"
                        placeholder="email@example.com"
                    />
                    <InputError message={errors.email} />
                </div>

                <div class="my-6 flex items-center justify-start">
                    <Button
                        type="submit"
                        class="w-full"
                        disabled={processing}
                        data-test="email-password-reset-link-button"
                    >
                        {#if processing}<Spinner />{/if}
                        Отправить ссылку для сброса
                    </Button>
                </div>
            {/snippet}
        </Form>

        <div class="space-x-1 text-center text-sm text-muted-foreground">
            <span>Или вернуться ко</span>
            <TextLink href={login()}>входу</TextLink>
        </div>
    </div>
</AuthLayout>
