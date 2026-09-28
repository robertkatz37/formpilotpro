# License suspension behavior

FormPilot Pro v7.1.0 uses signed server entitlements.

If the license owner suspends or revokes a license on the FormPilot License Manager:

1. The licensing server changes the license state immediately.
2. All existing activations for that license are marked `suspended` on the server.
3. Activation and validation requests are rejected while the license is suspended/revoked.
4. The client accepts a cryptographically signed suspended/revoked/expired response as authoritative and clears its offline grace period.
5. A temporary network outage is still eligible for the normal offline grace period when there is no authoritative inactive server decision.
6. Restoring the license to `active` restores the existing activation records to `active`, after which the client can validate normally.

Because WordPress installations do not provide a universal server-to-server push channel, a customer site cannot be forcibly changed at the exact millisecond of suspension. The server-side authorization is immediate; the customer plugin applies it on its next validation. The plugin performs a daily validation and administrators can use Check License Now.
