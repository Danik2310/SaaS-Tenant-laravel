<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ShoppingLi · Admin</title>

    @viteReactRefresh 

    @vite('resources/js/landlord/app.jsx')

    <style>
        body {
            margin: 0;
            background: #0A0A0A;
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen',
                'Ubuntu', 'Cantarell', 'Fira Sans', 'Droid Sans', 'Helvetica',
                'Arial', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        #landlord-root {
            width: 100%;
            min-height: 100vh;
        }
    </style>
</head>
<body>
    <div id="landlord-root"></div>
</body>
</html>