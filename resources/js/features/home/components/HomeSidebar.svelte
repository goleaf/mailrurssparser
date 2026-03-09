<script lang="ts">
    import {
        SidebarCategoryTree,
        SidebarDateCalendar,
        SidebarNewsletterBox,
        SidebarPopularArticles,
        SidebarTagCloud,
    } from '@/features/articles';
</script>

<aside class="space-y-6">
    <SidebarPopularArticles />
    <SidebarCategoryTree />
    <SidebarTagCloud />
    <SidebarDateCalendar />
    <SidebarNewsletterBox />
</aside>
