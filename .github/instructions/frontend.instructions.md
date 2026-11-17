---
applyTo: "resources/js/**/*.{js,jsx,ts,tsx}"
---

# Frontend instructions

This repo uses React 18 + Inertia + MUI + Tailwind.

- Use `useForm` for Inertia forms and avoid per-field `useState` for form state.
- Import MUI components individually instead of from a barrel.
- Every `IconButton` should have a `Tooltip` wrapper.
- Use `getRowId` when row selection is required and `enableRowActions` when `renderRowActions` is used in MRT v3.
- For Autocomplete, merge both `params.InputProps.ref` and `params.inputProps.ref`.
- Tooltips under jsdom render via portal; prefer `data-testid` assertions in tests over `getByTitle`.
- Keep UI data access via Inertia props; do not build ad hoc client-side fetches for core app state.
- Inertia pages must map to actual files in `resources/js/Pages/`.
- Route-level/component changes should keep the tenant/admin split intact between `resources/js/app.jsx` and `resources/js/landlord/app.jsx`.

Use the project architecture docs for tenancy/auth behavior before changing flows that affect the UI shell.
