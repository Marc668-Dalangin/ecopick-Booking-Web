# Live Update Pattern for CRUD Modules

This project uses a shared structure for live-refresh behavior across admin, seller, and junkshop pages.

## Goals

- Update only the changed UI region without a full page reload.
- Keep all read and write operations behind existing authentication and role checks.
- Reuse the current Bootstrap, stored-procedure, and CSRF architecture.
- Keep polling lightweight and scoped to the active role and page.

## Shared JavaScript utility

The project includes a reusable polling helper at `assets/js/live-updates.js`.

Use it like this:

```html
<script src="<?php echo APP_URL; ?>/assets/js/live-updates.js"></script>
<script>
  window.addEventListener('DOMContentLoaded', function () {
    window.EcoPickLiveUpdates.startPolling({
      key: 'admin-dashboard',
      url: '<?php echo APP_URL; ?>/admin-private-dnstl/api/dashboard.php',
      interval: 5000,
      onSuccess: function (payload) {
        if (!payload.success || !payload.data) return;
        // update only the affected cards or rows
      }
    });
  });
</script>
```

### Notes

- Each poller key is unique per page/feature.
- Pollers are deduplicated automatically.
- Polling pauses when the tab is hidden and resumes when visible.
- Polling avoids active form inputs to prevent overwriting user edits.
- The helper does not expose any private profile or password payloads.

## Secure JSON endpoint pattern

All live-update endpoints must:

1. Require the current session and role.
2. Validate CSRF for create/update/delete actions.
3. Use stored procedures for the database interaction.
4. Return JSON with `success`, `message`, `data`, and `validation_errors` where applicable.
5. Use safe, generic error messaging and never expose raw SQL or DB internals.
6. Redirect only on true session expiry.

Example response:

```json
{
  "success": true,
  "message": "Status updated successfully.",
  "data": {
    "stats": { "pending_junkshop_applications": 3 },
    "pending": []
  },
  "validation_errors": []
}
```

## CRUD update workflow

When an action completes:

- update only the affected row, badge, or card in the DOM;
- refresh the changed stats or list section;
- show a small success or error message; and
- keep the page in-place without a reload.

## Required live-refresh timing

- Poll only when data is relevant to the current page.
- Use a 5-second interval.
- Do not create duplicate timers.
- Use a single configurable utility so each feature follows the same pattern.

## Future modules

Any new CRUD feature should follow this same structure:

- page-specific HTML region; 
- JSON API route under the relevant area; 
- `fetch()` call with CSRF token support; 
- DOM-only patching for changed items; and
- polling utility for status/summary updates.
