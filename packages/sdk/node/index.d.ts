export type ShieldEnvironment = 'production' | 'sandbox';

export class Verifier {
    constructor(apiOrigin: string, secret: string);

    verify(token: string, siteKey: string, action: string, origin: string, environment?: ShieldEnvironment): Promise<boolean>;
}
