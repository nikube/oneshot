# Oneshot

Dolibarr module (id 185175) to run small one-off PHP scripts from the admin UI.
No more `docker cp` + `docker exec` for a ten-line data fix.

- Scripts live in `documents/oneshot/` (outside the web root), pasted from the module page.
- They run with `$db`, `$user`, `$conf`, `$langs` and `$args` (arguments split on spaces) in scope.
- Output is captured and displayed; exceptions are caught and printed.
- Administrators only.

See `examples/` for the script convention (works both from Oneshot and from the CLI).
