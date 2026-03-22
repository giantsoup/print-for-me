# Designer Handoff

This is a simple, invite-only web app for friends to request 3D prints. It is not meant to feel like a polished SaaS product or a heavy business platform. The goal is a lightweight, friendly web app that makes it easy to submit a print request, upload files, and check status without extra complexity.

The redesign should be mobile-first. Desktop still matters, but the UI can be simplified and designed from the phone experience upward.

## Core idea

- Guests can see a basic home page and request a magic login link.
- Users sign in without passwords.
- Users create private print requests with files and instructions.
- Users can view their requests, track status, and manage pending requests.
- Admins can review requests, move them through the workflow, and invite new users.

## Main screens

- Home / landing page
- Login / magic-link request
- Magic-link result / invalid-link state
- Dashboard
- Print requests list
- Print request detail
- New print request form
- Profile settings
- Appearance settings
- Verify email screen
- Admin invite screen
- Admin request list / workflow view

## Workflow

- `pending`
- `accepted`
- `printing`
- `complete`

## Screenshot sets

- Desktop references: [screenshots/desktop](/Users/Taylor/Sites/print-for-me/designer-handoff/screenshots/desktop)
- Mobile references: [screenshots/mobile](/Users/Taylor/Sites/print-for-me/designer-handoff/screenshots/mobile)

## Notes for design

- Keep the structure straightforward and low-friction.
- Prioritize clarity over feature density.
- Treat this as a personal/friends-use tool, not a formal SaaS dashboard.
- The upload/request flow is one of the most important parts of the UI.
