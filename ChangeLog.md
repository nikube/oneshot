# ChangeLog

## 0.2.0 (2026-10-28)

- Upload a .php file as an alternative to pasting (bypasses the anti-injection filter applied to POST fields).
- Security: save/run/delete only via POST forms, superadmin only under Multicompany, page refused when the module is disabled.
- Open transaction left by a script is rolled back; invalid name / empty content / failed delete now report an error.
- Source shown with htmlspecialchars (dol_escape_htmltag altered `<b>`/`<strong>` and entities on re-save).
- Examples: `Commande::cancel()` signature per version (`$user` first since 23.0), remove_products matches on product ids, reloads lines before revalidation.
- order_stock_out.php example: catch-up stock out for delivered orders without invoice (SuperStock).

## 0.1.1 (2026-09-11)

- remove_products.php: arguments are now product refs, orders are selected by the script (draft/validated, before a cutoff date), dry run by default, `go` to apply.

## 0.1.0 (2026-09-11)

- Initial release: paste, run with arguments, delete scripts from the admin page.
