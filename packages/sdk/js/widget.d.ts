export interface ShieldOptions {
    siteKey: string;
    action: string;
    locale?: 'en' | 'fa';
    theme?: 'light' | 'dark' | 'auto';
    mode?: 'checkbox' | 'managed';
    apiOrigin?: string;
    responseName?: string;
    guardSubmit?: boolean;
    onSuccess?: (detail: { token: string }) => void;
    onError?: (detail: { code: string }) => void;
    onExpire?: () => void;
}

export interface ShieldWidget {
    render(container: HTMLElement, options: ShieldOptions): string;
    execute(id: string): Promise<string | null>;
    getResponse(id: string): string;
    reset(id: string): void;
    remove(id: string): void;
    mountAll(root?: Document | HTMLElement): void;
}

declare global {
    interface Window {
        ShieldCaptcha: ShieldWidget;
    }
}
