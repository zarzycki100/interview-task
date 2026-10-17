What was done

1. Invoices module in a DDD / hexagonal layout: Domain, Application, Infrastructure,
   Presentation. The domain is plain PHP with no framework dependency, so business rules
   are unit-tested without a database. Layer boundaries are enforced by PHPat in PHPStan.
2. Invoices talk to Notifications only through its public Api, via a single adapter
   (NotificationsModuleInvoiceNotifier) behind an InvoiceNotifier port
3. Invoice is the aggregate: its status changes only through its own methods
   (send, markAsDelivered, revert to draft), which enforce the allowed transitions.
   An invoice without valid product lines is never sent.
4. Eloquent models are used only as persistence records. InvoiceMapper is the single
   translation point; stored data is rebuilt without re-validation, so invalid rows can
   still be loaded and are rejected when sendin
5. Optimistic locking (version column, conditional UPDATE) prevents two concurrent sends
   from e-mailing the customer twice. lockForUpdate is a no-op on SQLite, so it was not an option.
6. Send order fixed: following the README order, the DummyDriver confirms delivery before the
   invoice is in "sending", so the confirmation is lost and the invoice stays stuck.
   The status is now saved first, and a failed hand-off reverts the invoice to draft.
7. The webhook listener is registered in a non-deferred provider; a deferred one would never
   boot during a webhook request. Ignored confirmations return 204, technical errors 5xx
   so the provider retries.
8. Domain exceptions are mapped to HTTP codes in one place: ExceptionMapping.
9. Fixes to the provided setup: start.sh failed on a fresh clone, port 5173 conflict,
   PHP 8.5 PDO deprecation, missing APP_KEY generation.
10. 102 tests (unit, feature, architecture); `composer check` runs Pint, PHPStan (max) and PHPUnit.
11. Postman collection in postman/ covering the main flow and error cases - only because it's not a production env


I made the design decisions; Claude Code was used to implement them and to review bthe project for problems
