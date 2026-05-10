<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $isDeleted ? 'Account Deleted' : 'Account Suspended' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            color: #0f172a;
        }
        .card {
            background: #ffffff;
            border-radius: 12px;
            padding: 48px;
            max-width: 480px;
            width: 90%;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
        }
        .icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 28px;
        }
        .icon.suspended { background: #fef2f2; color: #ef4444; }
        .icon.deleted { background: #f1f5f9; color: #64748b; }
        h1 { font-size: 20px; font-weight: 700; margin-bottom: 8px; }
        p { font-size: 14px; color: #64748b; line-height: 1.6; margin-bottom: 24px; }
        .tenant-name {
            display: inline-block;
            background: #f1f5f9;
            color: #475569;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon {{ $isDeleted ? 'deleted' : 'suspended' }}">
            {{ $isDeleted ? '🗑️' : '⏸️' }}
        </div>
        <div class="tenant-name">{{ $tenantName }}</div>
        <h1>{{ $isDeleted ? 'This Account Has Been Deleted' : 'Account Suspended' }}</h1>
        <p>
            @if ($isDeleted)
                This account has been permanently deleted. If you believe this is a mistake,
                please contact the administrator for assistance.
            @else
                Your account has been temporarily suspended. Please contact support
                to resolve this issue and regain access.
            @endif
        </p>
    </div>
</body>
</html>
