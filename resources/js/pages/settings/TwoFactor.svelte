<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import ShieldBan from 'lucide-svelte/icons/shield-ban';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import { onDestroy } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.svelte';
    import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import SettingsLayout from '@/layouts/settings/Layout.svelte';
    import { twoFactorAuthState } from '@/lib/twoFactorAuth.svelte';
    import { disable, enable, show } from '@/routes/two-factor';
    import type { BreadcrumbItem } from '@/types';

    let {
        requiresConfirmation = false,
        twoFactorEnabled = false,
    }: {
        requiresConfirmation?: boolean;
        twoFactorEnabled?: boolean;
    } = $props();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Двухфакторная аутентификация',
            href: show(),
        },
    ];

    const twoFactorAuth = twoFactorAuthState();
    let showSetupModal = $state(false);

    onDestroy(() => {
        twoFactorAuth.clearTwoFactorAuthData();
    });
</script>

<AppHead title="Двухфакторная аутентификация" />

<AppLayout {breadcrumbs}>
    <h1 class="sr-only">Настройки двухфакторной аутентификации</h1>

    <SettingsLayout>
        <div class="space-y-6">
            <Heading
                variant="small"
                title="Двухфакторная аутентификация"
                description="Управляйте настройками двухфакторной аутентификации"
            />

            {#if !twoFactorEnabled}
                <div class="flex flex-col items-start justify-start space-y-4">
                    <Badge variant="destructive">Выключена</Badge>

                    <p class="text-muted-foreground">
                        После включения двухфакторной аутентификации система
                        будет запрашивать одноразовый код при входе. Код можно
                        получать в приложении-аутентификаторе на телефоне.
                    </p>

                    <div>
                        {#if twoFactorAuth.hasSetupData()}
                            <Button onclick={() => (showSetupModal = true)}>
                                <ShieldCheck class="size-4" />Продолжить
                                настройку
                            </Button>
                        {:else}
                            <Form
                                {...enable.form()}
                                onSuccess={() => (showSetupModal = true)}
                            >
                                {#snippet children({ processing })}
                                    <Button type="submit" disabled={processing}>
                                        <ShieldCheck class="size-4" />Включить
                                        2FA
                                    </Button>
                                {/snippet}
                            </Form>
                        {/if}
                    </div>
                </div>
            {:else}
                <div class="flex flex-col items-start justify-start space-y-4">
                    <Badge variant="default">Включена</Badge>

                    <p class="text-muted-foreground">
                        При включённой двухфакторной аутентификации система
                        будет запрашивать одноразовый код при входе, который
                        показывает приложение-аутентификатор на телефоне.
                    </p>

                    <TwoFactorRecoveryCodes />

                    <div class="relative inline">
                        <Form {...disable.form()}>
                            {#snippet children({ processing })}
                                <Button
                                    variant="destructive"
                                    type="submit"
                                    disabled={processing}
                                >
                                    <ShieldBan class="size-4" />
                                    Выключить 2FA
                                </Button>
                            {/snippet}
                        </Form>
                    </div>
                </div>
            {/if}

            <TwoFactorSetupModal
                bind:isOpen={showSetupModal}
                {requiresConfirmation}
                {twoFactorEnabled}
            />
        </div>
    </SettingsLayout>
</AppLayout>
