# WebBlocks Support

Provider-independent support tickets for WebBlocks CMS. The plugin owns the
Support admin menu, activation state, encrypted installation credential and
ticket UI. CMS core contains no support-provider or commercial workflow.

The provider contract is documented in `docs/webblocks-support-protocol-v1.md`.
An installation begins activation only after its administrator enters a
single-use invitation code supplied by the provider. Approval is then handled
by the provider operator while the CMS polls from its own Support screen; CMS
users do not need a provider account.
