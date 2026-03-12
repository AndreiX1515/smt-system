## Email (SMTP) setup for password reset

This project can send password reset links via SMTP (Gmail-ready).

### Required environment variables

Set these on the server (recommended), or in your process manager:

- `SMTP_HOST` = `smtp.gmail.com`
- `SMTP_PORT` = `587`
- `SMTP_USER` = your Gmail address (e.g. `yourname@gmail.com`)
- `SMTP_PASS` = **Gmail App Password** (not your normal login password)
- `SMTP_FROM` = (optional) sender email, defaults to `SMTP_USER`
- `SMTP_FROM_NAME` = (optional) sender name, defaults to `Smart Travel`

Optional (dev/testing):

- `MAIL_DEBUG_RETURN_LINK` = `1` to include `resetUrl` in API JSON response.

### Gmail notes

- Use a Google account with **2‑Step Verification enabled**, then create an **App Password**.
- Use that App Password for `SMTP_PASS`.

### Fallback behavior

If SMTP is not configured or sending fails, the server will still generate the reset token and **log the reset URL** to server logs (for testing), but `mailSent` will be `false`.

