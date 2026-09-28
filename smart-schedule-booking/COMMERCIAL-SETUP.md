# FormPilot Pro 7.1 commercial setup

## Architecture
- `https://formpilot.healthsdriven.com` is the private commercial control plane.
- Install **FormPilot License Manager** only on that server. Never ship it to customers.
- Ship **FormPilot Pro** to customers.
- Customer sites send only license/domain/plugin metadata to the license server. Form submissions and patient data are not sent.

## Customer flow
1. Customer opens the pricing page containing `[formpilot_pricing]`.
2. Stripe Checkout is created by the License Manager.
3. Stripe webhook marks the order/license paid and active.
4. Customer receives a license key and account/password-reset link.
5. Customer installs FormPilot Pro and enters the key under FormPilot > License.
6. The license server records the domain activation.
7. The client verifies the server's RSA signature using the embedded public key.
8. WordPress checks the commercial update endpoint. A short-lived download token is issued only for an active licensed domain.
9. Customer can view licenses and activated websites using `[formpilot_account]`.

## Required configuration
- HTTPS on `formpilot.healthsdriven.com`.
- Stripe secret key in License Manager > Settings.
- Stripe webhook signing secret in License Manager > Settings.
- Stripe webhook URL: `/wp-json/formpilot/v1/stripe/webhook`.
- Create a public pricing page with `[formpilot_pricing]`.
- Create a customer account page with `[formpilot_account]`.
- Publish at least one plugin ZIP in License Manager > Releases before expecting automatic updates.

## Important security rule
The License Manager plugin contains the RSA private signing key. Keep this plugin only on your controlled FormPilot server. If the private key is ever exposed, rotate the key pair and rebuild the client with the new public key.

## Domain policy
A license activation is bound to the normalized hostname. `www.example.com` is normalized to `example.com`. A different domain consumes another activation slot.

## Limitations
No PHP plugin distributed to a customer-controlled WordPress server can be made mathematically unmodifiable. This architecture protects the license authority, signed entitlements, update authorization, and private signing key. For stronger source protection, use ionCube or move especially sensitive premium operations to a server-side service.
