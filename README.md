# Oneshot

Dolibarr module (id 193947) to run small one-off PHP scripts from the admin UI.
No more `docker cp` + `docker exec` for a ten-line data fix.

- Scripts live in `documents/oneshot/` (outside the web root), pasted or uploaded from the module page.
  Prefer upload for scripts containing strings such as `<script` or `</textarea`: pasted content goes through the Dolibarr anti-injection filter, uploaded files do not.
- They run with `$db`, `$user`, `$conf`, `$langs` and `$args` (arguments split on spaces) in scope.
- Output is captured and displayed; exceptions are caught and printed.
- Administrators only (superadmin only when Multicompany is enabled); save/run/delete are POST + token only.
- A transaction left open by a script is rolled back and reported. `exit`/`die` in a script ends the page without output: use `return`.

See `examples/` for the script convention (works both from Oneshot and from the CLI).
