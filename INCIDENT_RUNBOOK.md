# Incident runbook

1. Record observed symptoms, deployment version, time, sanitized request IDs and incident owner. Use `/health/ready`, application logs and host indicators to distinguish routing, database, saturation and integration issues.
2. For a suspected key leak, revoke the affected credential, issue a replacement after reauthentication, and update the consumer. Confirm the old key is rejected and run an owned sandbox test. Revoke both relevant scopes when affected.
3. For a suspected isolation incident or abusive integration, suspend the affected organization or site. Preserve protected evidence, audit entries and access controls. Identify the affected interval and tenants before resuming access.
4. For a service outage, keep sensitive customer workflows closed or use a separately authenticated recovery process. For availability problems, check host capacity, database connections, cron overlap and proxy source configuration.
5. When reverting a broken release, restore matching application and database versions in an isolated environment using the original configuration key. Run preflight, a sandbox verification, and the tenant restrictions before restoring routing.
6. Record impact, recovery and preventative work. Use the deployment's approved, private communication process.

For payment incidents, disable new billing checkouts if needed, preserve existing invoice records, and reconcile pending provider references before changing a paid term or switching merchant configuration. For SMS incidents, disable delivery globally or per site, revoke the site's sending credential, and rotate the provider key if it may have leaked. Reconcile an ambiguous delivery in the provider account before allowing a replacement send.
