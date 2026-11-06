# WebBlocks Support

Provider-independent support tickets for WebBlocks CMS. The plugin owns the
Support admin menu, activation state, encrypted installation credential and
ticket UI. CMS core contains no support-provider or commercial workflow.

The provider contract is documented in `docs/webblocks-support-protocol-v1.md`.
An installation begins activation only after its administrator enters a
single-use invitation code supplied by the provider. Approval is then handled
by the provider operator while the CMS polls from its own Support screen; CMS
users do not need a provider account.

## Recovery support and reviewed reports

Support 0.4.0 requires CMS ^1.125.0. It adds impact, problem onset and last-action
context. System-wide diagnostic sharing additionally requires `access-system`
and `webblocks-support.share-system-diagnostics`; normal ticket access alone
cannot prepare or share these reports. Report preparation and final sharing are
separate explicit actions. Reports expire after 30 minutes and downloads reuse
the reviewed artifact without collecting new information.

A provider may advertise `ticket.recovery` and `ticket.idempotency` through the
existing Protocol 1.0 discovery document. Use **Check provider capabilities** on
an active connection to pick up new optional capabilities. New-capability
providers receive retry-safe ticket submissions and optional reviewed reports;
legacy providers continue to receive ordinary tickets with context in the body.
Review existing requests after uncertain delivery before retrying an older
provider. Unsent text is retained on failures.

Providers may advertise a same-origin HTTPS `support_portal_url`. Recovery ticket
receipts may include a same-origin private tracking link; the plugin stores it
encrypted and exposes it only to its ticket owner. **Follow request or share files
outside CMS** opens this provider page. General files are never added to the CMS
Media Library. The external provider controls contact verification, file handling,
retention, support entitlement and ownership review.

Capabilities use relational rows. Upgrading imports old connection capability
lists once without writing new values into the legacy JSON column. Provider and
tracking secrets remain encrypted and are excluded from diagnostics and exports.

### Local integration checks

Run `composer test` and `composer plugin:validate`. For behavioral checks, use a
local CMS checkout with installed test dependencies:

```sh
WEBBLOCKS_CMS_TEST_ROOT=/path/to/cms /path/to/cms/vendor/bin/phpunit -c /path/to/cms/phpunit.xml.dist --bootstrap tests/bootstrap-integration.php tests/SupportRecoveryTest.php
```

The integration tests use synthetic users, reports and HTTP fakes; they send no
real support requests.
