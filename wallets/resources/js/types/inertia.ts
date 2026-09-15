export type AuthUser = {
    id: number;
    name: string;
    email: string;
};

export type FlashMessages = {
    success?: string | null;
    error?: string | null;
    import_errors?: string[];
};

export type SharedPageProps = {
    auth: {
        user: AuthUser | null;
    };
    flash: FlashMessages;
    errors: Record<string, string | string[]>;
};
