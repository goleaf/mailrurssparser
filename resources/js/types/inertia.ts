export type HrefLike = string | { url: string };

export type HttpMethod = 'get' | 'post' | 'put' | 'patch' | 'delete';

export type InertiaVisitOptions = {
    method?: string;
    viewTransition?: boolean;
};
