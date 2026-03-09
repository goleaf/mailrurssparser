<script lang="ts">
    import { ArticleDetailPageContainer } from '@/features/articles';

    let { slug }: { slug: string } = $props();
</script>

<ArticleDetailPageContainer {slug} />
