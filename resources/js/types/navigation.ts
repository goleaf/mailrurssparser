import type { Component } from 'svelte';
import type { HrefLike } from './inertia';

type NavIcon = Component<{ class?: string }>;

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
