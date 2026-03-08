<script lang="ts">
    import AppearanceTabs from '@/components/AppearanceTabs.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import SettingsLayout from '@/layouts/settings/Layout.svelte';
    import { edit as editAppearance } from '@/routes/appearance';
    import type { BreadcrumbItem } from '@/types';

    const breadcrumbItems: BreadcrumbItem[] = [
        {
            title: 'Настройки оформления',
            href: editAppearance(),
        },
    ];
</script>

<AppHead title="Настройки оформления" />

<AppLayout breadcrumbs={breadcrumbItems}>
    <h1 class="sr-only">Настройки оформления</h1>

    <SettingsLayout>
        <div class="space-y-6">
            <Heading
                variant="small"
                title="Настройки оформления"
                description="Выберите внешний вид интерфейса для своего аккаунта"
            />
            <AppearanceTabs />
        </div>
    </SettingsLayout>
</AppLayout>
