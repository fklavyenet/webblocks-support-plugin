# Changelog

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
