<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Error code index</title>
    <style>
        body { font-family: Georgia, serif; margin: 2rem auto; max-width: 960px; color: #1c1917; background: #faf7f2; }
        h1 { font-size: 1.8rem; }
        p { max-width: 42rem; }
        input { width: 100%; padding: 0.6rem 0.8rem; margin: 1rem 0; border: 1px solid #d6d3d1; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { text-align: left; padding: 0.7rem; border-bottom: 1px solid #e7e5e4; vertical-align: top; }
        code { font-family: ui-monospace, monospace; }
        a { color: #9a3412; }
    </style>
</head>
<body>
    <h1>API error code index</h1>
    <p>Every API response carries a <code>code</code> equal to the HTTP status, which clients use directly, and a <code>trace_code</code> from this index. The prefix identifies the module and action, so a code such as <code>PAY-PRC-002</code> points at payment processing without reading the stack trace. When a request fails, the client reports <code>trace_code</code>. The real exception is stored in the logs under the response <code>trace_id</code>.</p>
    <p><a href="/docs">Interactive API reference</a></p>
    <input id="filter" type="search" placeholder="Filter by code or message" aria-label="Filter error codes">
    <table>
        <thead>
            <tr><th>Code</th><th>HTTP</th><th>Message</th></tr>
        </thead>
        <tbody id="codes">
            @foreach ($codes as $code)
                <tr>
                    <td><code>{{ $code['code'] }}</code></td>
                    <td>{{ $code['status'] }}</td>
                    <td>{{ $code['message'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <script>
        document.getElementById('filter').addEventListener('input', function (event) {
            const term = event.target.value.toLowerCase();
            document.querySelectorAll('#codes tr').forEach(function (row) {
                row.hidden = term !== '' && !row.innerText.toLowerCase().includes(term);
            });
        });
    </script>
</body>
</html>
