# WebBlocks Support Protocol 1.0

WebBlocks CMS and other products use this protocol to connect an installation
to a support provider without giving the installation an organization-wide
credential. WebBlocks Workbench is one provider; agencies may implement the
same contract on their own HTTPS origin.

## Discovery

`GET /.well-known/webblocks-support` returns JSON:

```json
{
  "protocol": "webblocks-support",
  "version": "1.0",
  "name": "Example Support",
  "api_base_url": "https://support.example.com/api/webblocks-support/v1",
  "capabilities": ["ticket.create", "ticket.list", "ticket.read", "ticket.reply", "diagnostics.request", "diagnostics.consent"],
  "activation_methods": ["invitation_code"]
}
```

The discovery URL and API base URL must use the same public HTTPS origin.
Redirects are not followed. CMS 1.0 requires all four ticket
capabilities.

## Installation activation

`POST {api_base_url}/activations` accepts:

```json
{
  "install_ref": "random-install-uuid",
  "product": "webblocks-cms",
  "product_version": "1.74.0",
  "site_url": "https://example.com",
  "environment": "production",
  "invitation_code": "WBS-ABCD-EFGH-IJKL"
}
```

The invitation must be valid, unused and issued for the requested product. It
is consumed atomically when the activation is created. The provider returns an
activation secret for polling and a user-facing reference code:

```json
{
  "activation_id": "act_123",
  "activation_secret": "one-install-polling-secret",
  "user_code": "ABCD-EFGH",
  "expires_at": "2026-08-28T14:00:00Z"
}
```

No provider login or external activation page is required. The provider
operator reviews the invitation-backed request and the CMS polls
`GET {api_base_url}/activations/{activation_id}` with the
activation secret as a bearer token. A pending response is
`{"status":"pending"}`. Once approved it returns:

```json
{
  "status": "active",
  "credential": "installation-scoped-bearer-secret",
  "plan_name": "Support",
  "entitlement_expires_at": "2027-08-28T00:00:00Z"
}
```

The credential must be limited to one product and installation. It must not
allow organization, project, plan or other installation administration.

## Tickets

All ticket calls authenticate with the installation credential. The endpoints
are relative to `api_base_url`:

- `POST /tickets`
- `GET /tickets?external_user_ref=...&install_ref=...`
- `GET /tickets/{ticket}?install_ref=...`
- `POST /tickets/{ticket}/comments`
- `DELETE /installation` to revoke the installation credential

Ticket creation includes `title`, `body`, `type`, `external_user_ref`,
`external_user_name`, `install_ref`, `product`, `product_version`, `site_url`
and `environment`. The provider derives its project and entitlement from the
credential; the client never supplies a project id.

Ticket reads must be scoped by credential and `install_ref`. The CMS also
checks `external_user_ref` before showing a ticket, so one administrator cannot
read another administrator's ticket by guessing its id.

## Consent-based diagnostics

A provider advertising `diagnostics.request` may include pending
`diagnostic_requests` in `GET /tickets/{ticket}`. Each request contains an
opaque id and an allowlisted set of categories: `system_summary`,
`recent_application_errors`, and `plugin_health`.

The installation must show those categories to the ticket owner and receive an
explicit approval before collecting or sending anything. It responds with
`POST /tickets/{ticket}/diagnostics/{diagnostic}` and either
`{"action":"decline"}` or `{"action":"submit","snapshot":{...}}`.
The snapshot is capped at 64 KiB and may contain only the requested categories.

The protocol never accepts a filesystem path or arbitrary command. Diagnostic
collection excludes `.env`, credentials, cookies and full logs; recent error
lines are bounded and redacted locally before transmission. Providers retain
the request, consent and submission timestamps as an audit trail.

## Secret handling

Activation and installation credentials are server-to-server secrets. They
must never be returned to a browser, logged, placed in a site export or exposed
again in the UI. CMS stores them encrypted with its application key.
