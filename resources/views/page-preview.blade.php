<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $page->name }} – Preview</title>
    <style>body { font-family: sans-serif; max-width: 800px; margin: 2rem auto; padding: 0 1rem; }</style>
</head>
<body>
  <p style="color:#999;font-size:12px;">PREVIEW MODE — not published</p>
  <h1>{{ $page->name }}</h1>
  @foreach($page->blocks ?? [] as $block)
    <section style="border:1px solid #eee;padding:1rem;margin:1rem 0;border-radius:8px;">
      <strong style="font-size:11px;color:#888;">{{ strtoupper($block['type']) }}</strong>
      <pre style="font-size:12px;color:#555;">{{ json_encode($block['data'], JSON_PRETTY_PRINT) }}</pre>
    </section>
  @endforeach
</body>
</html>
