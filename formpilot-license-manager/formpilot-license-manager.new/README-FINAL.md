# FormPilot License Manager 3.1.0

Install only on `https://formpilot.healthsdriven.com`. Never distribute this plugin to customers because it contains the RSA private signing key.

## Product model

FormPilot Pro 8.0 has a real free tier: no license is required for the basic builder. Free sites are limited to 1 form and 25 submissions per month. Stripe, payment links, Google Calendar/Meet, advanced styling, analytics, routing, automations, team scheduling and the full premium template library require an active paid entitlement.

## Commercial feature catalog

The signed entitlement supports: forms, builder, templates, Stripe, payment links, Google Calendar/Meet, advanced styling, branding, custom CSS, analytics, routing, workflows, team scheduling, round robin, collective events, group events, single-use links, meeting polls, reminders, follow-ups, webhooks, API, CRM integrations, video integrations, GA4, Meta Pixel, contacts, invoices, updates and priority support.

The current 8.0 client implements the free-tier enforcement and premium template foundation while preserving the existing FormPilot booking/form/payment/calendar functionality. Additional Calendly-style modules should be added as separate feature modules so each can be independently entitled and updated.

## Stripe webhook

`/wp-json/formpilot/v1/stripe/webhook`

## Customer flow

Pricing → Stripe Checkout → customer account → license → domain activation → signed entitlement → authorized downloads/updates.
