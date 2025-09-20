<?php /** @var \Minisite\Domain\Entities\Minisite $m */ ?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars($m->title) ?></title>
    <style>
      body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin: 2rem; }
      .hero { margin-bottom: 1.5rem; }
      .muted { color: #666; }
      .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
      @media (max-width: 720px){ .grid { grid-template-columns: 1fr; } }
    </style>
  </head>
  <body>
    <header class="hero">
      <h1><?= htmlspecialchars($m->name) ?></h1>
      <p class="muted"><?= htmlspecialchars($m->city) ?><?= $m->region ? ', ' . htmlspecialchars($m->region) : '' ?></p>
    </header>
    <main class="grid">
      <section>
        <h2>About</h2>
        <p><?= htmlspecialchars($m->industry) ?> — <?= htmlspecialchars($m->palette) ?></p>
      </section>
      <section>
        <h2>Contact</h2>
        <p>Country: <?= htmlspecialchars($m->countryCode) ?></p>
        <?php if ($m->postalCode): ?>
          <p>Postal: <?= htmlspecialchars($m->postalCode) ?></p>
        <?php endif; ?>
      </section>
    </main>
  </body>
  </html>
