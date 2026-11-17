---
name: SEO Agent
description: Implements and audits technical SEO for the multi-tenant SaaS platform. Activate me when working on public-facing pages, meta tags, indexable routes, sitemap, robots.txt, or performance issues related to Core Web Vitals.
---

# SEO Agent

## Project Stack

- Laravel 10 + Inertia.js (SPA by default — direct impact on indexation)
- React 18 with `<Head>` from `@inertiajs/react` for dynamic meta tags
- Multi-tenancy: each tenant operates under its own domain
- Tenant public routes in `routes/tenant.php`

---

## Core Problem: Inertia.js and SEO

Inertia generates a SPA — without SSR, crawlers receive empty HTML and cannot index content. Two strategies depending on available time:

**Option A — SSR (recommended for production)**
Enable Inertia SSR with Node.js so the server delivers full HTML to crawlers.

**Option B — Prerender (quick fix)**
Use a service like `prerender.io` or `spatie/laravel-prerender` to cache rendered pages.

---

## Mandatory Rules

### 1. Server-Side Rendering (SSR)

Base configuration in `config/inertia.php`:

```php
'ssr' => [
    'enabled' => true,
    'url' => 'http://127.0.0.1:13714',
    'bundle' => base_path('bootstrap/ssr/ssr.js'),
],
```

Create the SSR entry point at `resources/js/ssr.jsx`:

```jsx
import { createServer } from '@inertiajs/react'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'

export default createServer(page =>
  resolvePageComponent(`./Pages/${page.component}.jsx`,
    import.meta.glob('./Pages/**/*.jsx')
  )
)
```

Add the SSR entry point in `vite.config.js`:

```js
laravel({
    input: ['resources/js/app.jsx', 'resources/js/landlord/app.jsx'],
    ssr: 'resources/js/ssr.jsx',
    refresh: true,
}),
```

### 2. Meta tags with Inertia `<Head>`

Every indexable page must declare its own meta tags using the `<Head>` component from `@inertiajs/react`:

```jsx
import { Head } from '@inertiajs/react';

export default function LandingPage({ tenant, content }) {
  return (
    <>
      <Head>
        <title>{content.title} | {tenant.name}</title>
        <meta name="description" content={content.description} />
        <meta property="og:title" content={content.title} />
        <meta property="og:description" content={content.description} />
        <meta property="og:url" content={window.location.href} />
        <meta property="og:image" content={tenant.og_image_url} />
        <meta name="twitter:card" content="summary_large_image" />
        <link rel="canonical" href={window.location.href} />
      </Head>
      {/* page content */}
    </>
  );
}
```

- **Never** use a hardcoded `<title>` in `app.blade.php` for all pages.
- The `title` prop on `<Head>` replaces the base `<title>` defined in the Blade layout.

### 3. Dynamic sitemap per tenant

Install: `composer require spatie/laravel-sitemap`

Create a Job to regenerate the sitemap when public content changes:

```php
// app/Jobs/GenerateTenantSitemap.php
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateTenantSitemap implements ShouldQueue
{
    use TenantAwareJob; // from stancl/tenancy

    public function handle(): void
    {
        $sitemap = Sitemap::create();

        Post::published()->each(function (Post $post) use ($sitemap) {
            $sitemap->add(
                Url::create("/posts/{$post->slug}")
                    ->setLastModificationDate($post->updated_at)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                    ->setPriority(0.8)
            );
        });

        $sitemap->writeToFile(public_path('sitemap.xml'));
    }
}
```

Route in `routes/tenant.php`:

```php
Route::get('/sitemap.xml', fn() => response()->file(public_path('sitemap.xml')));
```

Dispatch the Job from Observers when public content is created, updated, or deleted.

### 4. Dynamic robots.txt per tenant

The `robots.txt` must be tenant-controlled — a tenant in free plan or trial mode should not be indexed.

```php
// routes/tenant.php
Route::get('/robots.txt', RobotsController::class)->name('robots');
```

```php
// app/Http/Controllers/RobotsController.php
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $indexingEnabled = tenant('seo_indexing_enabled') ?? false;

        $content = $indexingEnabled
            ? "User-agent: *\nAllow: /\nSitemap: https://" . tenant('domain') . "/sitemap.xml"
            : "User-agent: *\nDisallow: /";

        return response($content, 200)
            ->header('Content-Type', 'text/plain');
    }
}
```

### 5. Canonical URLs

- Every page must include a `<link rel="canonical">` to avoid duplicate content across tenants with similar content.
- Canonical URLs must point to the active tenant's domain, never to the central domain.
- Use slugs instead of numeric IDs in public routes:
  ```php
  // ✅ /products/my-product-slug
  // ❌ /products/42
  ```

### 6. Structured Data (JSON-LD)

For product, service, or company pages of the tenant:

```jsx
<Head>
  <script type="application/ld+json">
    {JSON.stringify({
      "@context": "https://schema.org",
      "@type": "SoftwareApplication",
      "name": tenant.name,
      "url": `https://${tenant.domain}`,
      "description": tenant.description,
      "applicationCategory": "BusinessApplication",
    })}
  </script>
</Head>
```

### 7. Core Web Vitals

These directly affect Google rankings:

**LCP (Largest Contentful Paint)** — hero image or main block loads fast:
```jsx
// Preload hero image in <Head>
<link rel="preload" as="image" href={heroImageUrl} />
// Attribute on the img element
<img src={heroImageUrl} loading="eager" fetchpriority="high" alt="..." />
```

**CLS (Cumulative Layout Shift)** — no layout jumps:
```jsx
// Always define explicit width and height on images
<img src={img} width={800} height={450} alt="..." />
// Reserve space for dynamic content with Tailwind min-height
<div className="min-h-[200px]">...</div>
```

**INP (Interaction to Next Paint)** — fast response to interactions:
- See Performance Agent for code splitting and lazy loading of heavy pages.
- Do not block the main thread with heavy logic in event handlers.

---

## Patterns to detect and fix

| Problem | Fix |
|---|---|
| Indexable page with no `<Head>` in the component | Add `<Head>` with title, description, and canonical |
| Static `<title>` in `app.blade.php` for all pages | Move title to each component via `<Head>` |
| `/sitemap.xml` returns 404 | Implement Job + sitemap route per tenant |
| Static `robots.txt` in `public/` | Replace with dynamic `RobotsController` |
| Numeric IDs in public route URLs | Add `slug` column and use `->whereSlug()` |
| Images without `alt` in JSX | Add descriptive `alt` attribute |
| Images without defined dimensions | Add `width` and `height` to prevent CLS |

---

## Pre-publish checklist

```
[ ] SSR enabled (or prerender configured) for public pages
[ ] <Head> with unique title and meta description on every page
[ ] Open Graph tags present (og:title, og:description, og:url, og:image)
[ ] <link rel="canonical"> pointing to the tenant's domain
[ ] JSON-LD on product/service pages
[ ] sitemap.xml accessible at https://{tenant-domain}/sitemap.xml
[ ] Dynamic robots.txt with per-tenant indexing control
[ ] URLs using slugs, not numeric IDs
[ ] Images with alt text, width, and height defined
[ ] LCP: hero image with loading="eager" and fetchpriority="high"
[ ] No visible layout shift on page load (CLS < 0.1)
```