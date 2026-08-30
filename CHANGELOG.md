# Changelog

## 0.3.2 - 2026-08-30

- Pass the diagnostic request identifier through to the provider endpoint when an administrator approves or declines access.
- Show provider failures inside the diagnostic consent card instead of silently returning to the unchanged ticket.

## 0.3.1 - 2026-08-30

- Send plugin health diagnostics under the protocol's requested `plugin_health` key, preventing approved diagnostic requests from being rejected by the provider.
- Render conversation messages entirely with native WebBlocks UI cards so the ticket remains usable without a separately published plugin stylesheet.
- Convert diagnostic provider failures into an in-context support error instead of an unhandled 500 response.

## 0.3.0 - 2026-08-30

- Add explicit-consent diagnostics to support tickets. Customers see the exact requested categories and can approve or decline before any information leaves their installation.
- Collect only allowlisted system summary, plugin state and a bounded recent-error tail; redact credentials, cookies, tokens and email addresses locally, and never accept arbitrary file paths or commands.

## 0.2.3 - 2026-08-30

- Bring the customer ticket conversation in line with the approved mockup using a plugin-owned, scoped admin stylesheet: compact message cards, avatars, requester/support distinction, readable content width, and a composer visually attached to the thread.
- Keep the styling inside the Support plugin and its published asset directory rather than adding plugin-specific rules to CMS core.

## 0.2.2 - 2026-08-30

- Fix the support ticket detail page failing with HTTP 500 because its empty-comment Blade branch compiled to an unterminated conditional.
- Keep the conversation-first ticket layout introduced in 0.2.1 while rendering tickets with any number of replies safely.

## 0.2.1 - 2026-08-30

- Redesign support ticket detail around one chronological conversation, with the original request as its first message and clearer requester/support-team distinction.
- Move status into the compact ticket header, show who is expected to reply next, and reduce reply-success chrome that displaced the conversation.

## 0.2.0 - 2026-08-29

- Require a single-use support invitation code before an installation can create an activation request.
- Keep activation and approval inside the CMS Support screen; remove the provider login/activation-page action.
- Send no request to the provider until both its HTTPS address and an invitation code are submitted.

## 0.1.2

- Keep the route-file view fallback compatible with an already-loaded 0.1.0 provider during catalog updates.

## 0.1.1

- Register the plugin view namespace from the CMS-loaded definition so manually installed and route-cached installations can render Support pages.
- Report healthy or setup-required plugin health from the support connection table and registered Support view.

## 0.1.0

- Extract the provider-independent support connection and ticket workflow from WebBlocks CMS core into an optional catalog plugin.
