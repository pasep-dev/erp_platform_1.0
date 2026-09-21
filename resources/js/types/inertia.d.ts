import type { PageProps as InertiaPageProps } from '@inertiajs/core';

interface AppContext {
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    timezone: string;
    date_format: string;
    datetime_format: string;
}

interface AuthContext {
    user: {
        id: number;
        name: string;
        email: string;
        is_active: boolean;
        email_verified_at: string | null;
        last_login_at: string | null;
        last_login_ip: string | null;
    } | null;
}

interface FlashContext {
    success?: string | null;
    error?: string | null;
}

declare module '@inertiajs/core' {
    interface PageProps extends InertiaPageProps {
        app: AppContext;
        auth: AuthContext;
        flash: FlashContext;
    }
}

export {};