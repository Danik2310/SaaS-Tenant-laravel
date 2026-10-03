<?php

declare(strict_types=1);

return [
    'trial' => [
        'summary' => 'Every paid feature with no caps, for 14 days. No card, no charge.',
        'footnote' => 'Ends on its own and drops to Free. Nothing is charged unless you pick a paid plan.',
    ],
    'free' => [
        'summary' => 'One location, one clean catalog and a stock overview. Movement tracking starts on Growth.',
        'footnote' => 'Free forever. No card, no expiry. Upgrade when the shop outgrows it.',
    ],
    'growth' => [
        'summary' => 'For a shop done with spreadsheets: every stock movement recorded, with room for a small team.',
        'footnote' => 'Billed monthly. Cancel any time — your catalog and history stay put.',
    ],
    'pro' => [
        'summary' => 'For multi-location teams: bulk edits, a custom domain and advanced reporting.',
        'footnote' => 'Billed monthly. Everything in Growth, with higher limits.',
    ],
    'enterprise' => [
        'summary' => 'For teams at scale: no cap on users, warehouses, products, categories or storage.',
        'footnote' => 'Billed yearly. Priority support and a named contact for onboarding.',
    ],
];
