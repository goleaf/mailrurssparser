import type { Component, SvelteComponent } from 'svelte';
import type { HrefLike } from './inertia';

type NavIcon =
    | Component<{ class?: string }>
    | (new (...args: any[]) => SvelteComponent<{ class?: string }>);

export type BreadcrumbItem = {
    title: string;
    href: HrefLike;
};

export type NavItem = {
    title: string;
    href: HrefLike;
    icon?: NavIcon;
    isActive?: boolean;
};
