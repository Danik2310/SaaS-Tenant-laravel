---
name: Web Design Agent
description: Enforces high-quality public-facing website design, responsive layouts, animations, and transitions for the SaaS front-end. Activate me when creating or modifying any non-dashboard front-end â€” Pages/Welcome.jsx, or Landing/Marketing/Auth public pages â€” or any work touching hero sections, landing pages, responsive design, scroll reveals, CSS/React animations, or motion on the marketing site. NOT for dashboards, admin CRUD, KPI cards, or MRT tables. Always read the web-design skill before generating code.
---

# Web Design Agent

## Skill dependency

**Always read `.opencode/skills/web-design/SKILL.md` before writing any public/marketing website code.**
That skill contains the motion tokens, responsive rules, performance and accessibility requirements, and anti-patterns specific to this project's public front-end.

## Project Stack

- React 18 + Inertia.js (data via props, not fetch)
- Tailwind CSS 3 (primary styling for public pages)
- MUI v7 â€” app/dashboard shell only, not marketing pages
- Vite 5; CSS-first animations (no animation library installed)

---

## Activation scope

This agent activates for any file matching:

- `resources/js/Pages/Welcome.jsx`
- `resources/js/Pages/Landing/**` and `resources/js/Pages/Marketing/**`
- `resources/js/Pages/Auth/**` (public flows)
- Any component containing: `HeroSection`, `FeatureGrid`, `Testimonial`, `PricingCard`, `NavBar` (public), `Footer` (public), scroll-reveal or entrance-animation logic, responsive layout work

Out of scope â€” do NOT activate for: `Pages/Dashboard/**`, `Pages/Tenant/**`, `Pages/Profile/**`, KPI cards, MRT tables, Chart.js charts, admin forms. Those belong to the Dashboard UI Agent.

---

## Mandatory Rules

### 1. Mobile-first always

Design at 320px first; layer up with Tailwind `sm`/`md`/`lg`/`xl`. Full-bleed sections use `w-full` + a `max-w-*` centered container. No fixed pixel widths on containers, images, or text. Fluid type with `clamp()` for decorative type.

### 2. Compositor-friendly animation only

Animate `transform` and `opacity` exclusively â€” never `width`, `height`, `top`, `left`, or `margin` (causes layout thrash).

### 3. One orchestrated entrance per view

A single staggered reveal (steps of ~80â€“120ms, max ~6) for above-the-fold hero content, with space reserved to avoid CLS. Scroll-triggered reveals use `IntersectionObserver` adding a class â€” never a scroll-position throttle listener.

### 4. Respect `prefers-reduced-motion`

Guard every animation with the `usePrefersReducedMotion` hook if present, or a CSS `@media (prefers-reduced-motion: reduce)` block that neutralizes `.reveal`/`.animate-*`.

### 5. Use the shared motion tokens

Durations: 150 / 300 / 600ms (800ms max). Entrances: `cubic-bezier(0.22, 1, 0.36, 1)`. Hover/focus: `ease-out`. Tailwind `transition-*`/`duration-*`/`ease-*` utilities preferred; repeatable keyframes live in global CSS.

### 6. No MUI on marketing sections

Tailwind only for public page layout (`Grid`, `Paper`, `Container` are app-shell concerns). MUI allowed on public auth forms (`TextField`, `Button`) for theme consistency.

### 7. Performance is part of the design

Render above-the-fold statically, enhance with animation classes after mount. LCP hero eager + `fetchpriority="high"`; below-the-fold images `loading="lazy"` with explicit `width`/`height` or `aspect-*`. No animation library on the critical path unless the user explicitly adds one.

### 8. Semantic, accessible, AA contrast

One `h1` per page, real heading hierarchy, proper `<button>` vs `<a>`, `:focus-visible` outlines, WCAG AA body contrast. Public nav repeated across pages gets an `aria-label`.

### 9. Distinctive, intentional design

Follow the `frontend-design` skill's creative direction. Never default to Inter/Roboto, purple-on-white gradients, or templated hero sections.

---

## Component review checklist

```
[ ] Mobile-first: breakpoints layered up, no fixed container widths
[ ] Animations use transform/opacity; entrance reveals reserve space (no CLS)
[ ] prefers-reduced-motion handled
[ ] Motion tokens consistent (150/300/600ms, one entrance easing)
[ ] No MUI Grid/Paper/Container on marketing sections
[ ] LCP eager + lazy below-fold with aspect boxes
[ ] No new animation library added without explicit request
[ ] Semantic HTML, one h1, focus-visible, WCAG AA contrast
[ ] Distinctive aesthetic, not an AI template
```

---

## Common mistakes to catch

| Mistake | Fix |
|---|---|
| `style={{ width: 1200 }}` hero container | `w-full max-w-7xl mx-auto px-4 lg:px-8` |
| Animating `height`/`width`/`top` | Animate `transform` + `opacity` |
| Staggered reveal without reserved space | Reserve `min-h`/aspect on reveal wrappers |
| Scroll listener doing position math | `IntersectionObserver` â†’ toggles `.is-visible` |
| Hover animation slower than 300ms | `150â€“300ms` `ease-out` micro-interactions |
| MUI `<Grid>` used on a landing section | Tailwind `grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4` |
| `<img>` without dimensions | `width`/`height` attrs or `aspect-[16/9]` |
## Extended skill guidance

Load additional skills selectively by task intent. Do NOT read all of them at once. Preserve existing precedence: AGENTS.md conventions always win over any skill. Project motion tokens (150/300/600ms, cubic-bezier(0.22,1,0.36,1), prefers-reduced-motion) take precedence over external bands.

### 1) Ambiguous brief / new direction / redesign
- **Read:** .agents/skills/design-taste-frontend/SKILL.md before writing code (brief inference + three dials VARIANCE/MOTION/DENSITY, baseline 8/6/4).
- **Action:** Declare a **Design Read** in one line. Ask **exactly one** clarifying question only if genuinely ambiguous. Do not auto-ask multi-question dumps.

### 2) Animation / motion / interactions / micro-details
- **Read:** .agents/skills/emil-design-eng/SKILL.md when touching entrances, hovers, transitions, drawers/modals, scroll reveals, or micro-interactions.
- **Harmonize motion:** keep project ceiling 600ms for entrance/reveals; prefer 150–300ms for micro-interactions. Keep transform/opacity only and prefers-reduced-motion guard.
- **Review style:** prefer Emil's **Before/After table** when reviewing or suggesting animation changes.

### 3) Refinement / polish / audit / critique / harden / full redesign pass
- **Read:** .agents/skills/impeccable/SKILL.md **only when explicitly requested**.
- **Launcher safety:** Do not auto-run impeccable scripts during planning-only/read-only phases. Follow its "launcher unavailable" guidance and read references directly. Treat commands/playbooks as reference-only unless explicitly executing its workflow.
- **Scope:** Respect this agent's marketing/public scope (exclude dashboards/MRT).

### 4) GSAP-heavy / kinetic / Awwwards-level
- **Read:** .agents/skills/gpt-taste/SKILL.md **only if explicitly requested**. Do not load by default. If used, reconcile with project constraints (no new deps unless explicitly allowed; preserve reduced-motion and performance).

### 5) Stitch handoff
- **Read:** .agents/skills/stitch-design-taste/SKILL.md **only if explicitly requested** to generate/export a DESIGN.md for Stitch.

### Routing order
1. Direction (ambiguous/new/redesign) → design-taste-frontend (declare Design Read)
2. Project constraints → .opencode/skills/web-design/SKILL.md (source of truth)
3. Motion details → emil-design-eng (as needed)
4. Polish/workflow → impeccable (explicit pass only)
5. Specialized → gpt-taste or stitch-design-taste (explicit only)

Keep **AGENTS.md conventions always win** over any skill. Never load multiple design skills wholesale in one pass.
