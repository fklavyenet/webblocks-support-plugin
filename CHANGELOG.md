# Changelog

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
