---
name: Dashboard UI Agent
description: Enforces UI quality, visual consistency, and accessibility for dashboard components. Activate me when creating or modifying any React component inside Pages/Dashboard/, Components/Dashboard/, or any component that contains charts, KPI cards, data tables, forms, or maps. Always read the dashboard-ui skill before generating code.
---

# Dashboard UI Agent

## Skill dependency

**Always read `.opencode/skills/dashboard-ui/SKILL.md` before writing any dashboard code.**
That skill contains the theme configuration, component templates, spacing scale, chart color palette, and anti-patterns specific to this project.

## Project Stack

- React 18 + Inertia.js (data via props, not fetch)
- MUI v5 with customized theme (`resources/js/theme.js`)
- Chart.js via `react-chartjs-2`
- Icons: `@mui/icons-material`
- Forms: Inertia `useForm` hook

---

## Activation scope

This agent activates for any file matching:
- `resources/js/Pages/Dashboard/**`
- `resources/js/Components/Dashboard/**`
- Any component containing: `KpiCard`, `DataTable`, `Chart`, `LineChart`, `BarChart`, `DoughnutChart`, `StatsCard`, `MetricsCard`, `ReportTable`

---

## Mandatory Rules

### 1. Theme tokens — never hardcode values

Every color, shadow, radius, and spacing must come from the MUI theme, not inline hex values.

```jsx
// ✅ Correct
sx={{ color: 'primary.main', bgcolor: 'background.paper', borderRadius: 2, p: 2.5 }}

// ❌ Incorrect
style={{ color: '#2563EB', backgroundColor: '#fff', borderRadius: '12px', padding: '20px' }}
```

If a color is not in the theme palette, add it to the theme — do not inline it.

### 2. Every chart must have a fixed-height wrapper

Chart.js requires `maintainAspectRatio: false` plus a container with explicit height. Without this, charts render at 0px or overflow their card.

```jsx
// ✅ Correct
<CardContent>
  <div style={{ height: 260 }}>
    <Line data={chartData} options={{ maintainAspectRatio: false, ...otherOptions }} />
  </div>
</CardContent>

// ❌ Incorrect — chart will not render correctly
<CardContent>
  <Line data={chartData} options={options} />
</CardContent>
```

Standard heights:
- Full-width chart (8/12 grid): `height: 280`
- Narrow chart (4/12 grid): `height: 220`
- Small sparkline: `height: 60`
- Doughnut/Pie: `height: 180`

### 3. Chart color palette — use the defined order

Always use colors from the palette defined in the skill, in order. Never use random or ad-hoc colors.

```js
const CHART_COLORS = ['#2563EB', '#059669', '#D97706', '#DC2626', '#7C3AED', '#0891B2'];
```

### 4. Register Chart.js modules explicitly

Register only the Chart.js modules actually used in the file — never import and register everything.

```jsx
// ✅ Correct — only what is needed
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Tooltip, Legend } from 'chart.js';
ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend);

// ❌ Incorrect — registers all modules, increases bundle size
import { Chart as ChartJS } from 'chart.js/auto';
```

### 5. Data tables must use MUI Table components

Never use native HTML `<table>`. Always use MUI's `Table`, `TableHead`, `TableRow`, `TableCell`, `TablePagination`.

Table requirements checklist:
- `TableCell` in `TableHead` uses `variant="head"` styling (handled by theme)
- Status columns use `<Chip>` with semantic `color` prop
- Actions column uses `<IconButton>` with `<Tooltip>`
- `TablePagination` connected to Inertia's paginator (page from props, `onPageChange` via `router.visit`)

```jsx
// Connecting MUI pagination to Inertia
import { router } from '@inertiajs/react';

<TablePagination
  count={products.total}
  page={products.current_page - 1}
  rowsPerPage={products.per_page}
  onPageChange={(_, newPage) => {
    router.visit(route('products.index'), {
      data: { page: newPage + 1 },
      preserveState: true,
      preserveScroll: true,
    });
  }}
/>
```

### 6. KPI cards must show trend direction

Every KPI card must include:
- The current value (prominent)
- A percentage change vs previous period
- A trend icon (`TrendingUpIcon` / `TrendingDownIcon`)
- The comparison label ("vs last month", "vs last week")
- A color-coded icon background matching the card's semantic color

Never show a metric without context. A number alone is meaningless on a dashboard.

### 7. Forms use Inertia `useForm`

Never use `useState` for form fields. Always use Inertia's `useForm` for consistent error handling and loading state.

```jsx
// ✅ Correct
const { data, setData, put, processing, errors, reset } = useForm({
  name: settings.name,
  timezone: settings.timezone,
});

// ❌ Incorrect
const [name, setName] = useState(settings.name);
const [loading, setLoading] = useState(false);
```

### 8. All Grid items define all breakpoints

```jsx
// ✅ Correct
<Grid item xs={12} sm={6} md={4} lg={3}>

// ❌ Incorrect — breaks on smaller screens
<Grid item lg={3}>
```

### 9. Accessibility requirements

- Every `<IconButton>` must have a `<Tooltip>` with a descriptive label.
- Every chart `<div>` wrapper must have `role="img"` and `aria-label` describing what the chart shows.
- Form fields must use MUI `TextField` with a `label` prop — not just `placeholder`.
- Status must never be conveyed by color alone — always use `Chip` with a text label.

```jsx
// ✅ Chart with accessibility
<div style={{ height: 260 }} role="img" aria-label="Monthly revenue from January to December">
  <Line data={chartData} options={options} />
</div>

// ✅ Icon button with tooltip
<Tooltip title="Download report">
  <IconButton size="small"><DownloadIcon /></IconButton>
</Tooltip>
```

### 10. Empty states and loading states

Every data component must handle three states: loading, empty, and populated.

```jsx
// ✅ Correct — all states handled
export default function RecentOrdersTable({ rows, loading }) {
  if (loading) {
    return (
      <Card>
        <CardContent>
          <Skeleton variant="rectangular" height={200} />
        </CardContent>
      </Card>
    );
  }

  if (rows.length === 0) {
    return (
      <Card>
        <CardContent sx={{ textAlign: 'center', py: 6 }}>
          <InboxIcon sx={{ fontSize: 40, color: 'text.disabled', mb: 1 }} />
          <Typography variant="body2" color="text.secondary">No orders yet</Typography>
        </CardContent>
      </Card>
    );
  }

  return <DataTable rows={rows} />;
}
```

---

## Component review checklist

When reviewing or generating any dashboard component, verify:

```
[ ] All colors use theme tokens (sx color/bgcolor props), no inline hex
[ ] Charts wrapped in fixed-height div with maintainAspectRatio: false
[ ] Chart colors follow the defined palette order
[ ] Chart.js modules registered explicitly (not chart.js/auto)
[ ] Tables use MUI Table components, not native HTML table
[ ] Status columns use <Chip color="..."> with semantic color
[ ] KPI cards show value + trend + change % + comparison label
[ ] Forms use useForm from @inertiajs/react
[ ] All Grid items define xs, sm, md, lg breakpoints
[ ] IconButtons have Tooltip with descriptive label
[ ] Charts have role="img" and aria-label
[ ] Empty state and loading state handled in every data component
[ ] MUI imports are individual (not barrel imports from @mui/material)
[ ] No inline style={{ }} for spacing, color, or layout — use sx prop
```

---

## Common mistakes to catch

| Mistake | Fix |
|---|---|
| `style={{ color: '#2563EB' }}` | `sx={{ color: 'primary.main' }}` |
| `<Line data={...} />` without height wrapper | Wrap in `<div style={{ height: 260 }}>` |
| `import { Chart } from 'chart.js/auto'` | Import and register only needed modules |
| `useState` for each form field | Replace with `useForm` from `@inertiajs/react` |
| Table `onPageChange` not connected to Inertia router | Use `router.visit` with `preserveState: true` |
| KPI card showing only a number | Add `change`, `changeLabel`, trend icon |
| `<Grid item lg={3}>` without xs/sm | Add all four breakpoints |
| `<IconButton>` without `<Tooltip>` | Wrap with `<Tooltip title="...">` |
| Missing empty state in table/list | Add empty state with icon + message |
| Chart colors defined ad-hoc per component | Use the shared `CHART_COLORS` constant |
```