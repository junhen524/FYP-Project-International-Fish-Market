<?php
$file = 'C:\xampp\htdocs\FisherySystem\International\about.php';
$lines = file($file);
array_splice($lines, 339, 628 - 339); // removes PHP lines 340-628 (0-indexed: 339-627)
// Now insert the deck HTML at position 339 (after the removed section)
$deckEnd = <<<'DECK'
    <!-- ── Deck Sequence ── -->
    <div class="app-container" id="app-root-viewport">

      <div class="fixed-hud">
        <div class="hud-dot"></div>
        <span style="font-weight:bold; letter-spacing:1px; color:rgba(255,255,255,0.9);">STICKY STACK V1.2</span>
        <div class="hud-divider"></div>
        <span id="hud-percent-indicator" style="color:#f97316; font-weight:bold;">0% FRAME</span>
      </div>

      <div class="deck-stage-wrapper" id="parallax-3d-stage">
        <div class="deck-radial-dots"></div>
        <div class="deck-watermark">Frame</div>

        <nav class="deck-header-nav">
          <div style="display:flex; flex-direction:column;">
            <span style="font-size:10px; font-family:'DM Mono',monospace; letter-spacing:0.3em; text-transform:uppercase; opacity:0.6;">Project Reference</span>
            <span style="font-size:24px; font-weight:900; text-transform:uppercase; font-style:italic; margin-top:4px;">Brand / Appart</span>
          </div>
          <div style="display:flex; gap:48px;">
            <div style="display:flex; flex-direction:column; align-items:flex-end;">
              <span style="font-size:10px; font-family:'DM Mono',monospace; letter-spacing:0.3em; text-transform:uppercase; opacity:0.6;">Sequence</span>
              <span id="deck-sequence-counter" style="font-size:20px; font-weight:bold; font-family:'DM Mono',monospace;">01 — 04</span>
            </div>
            <div class="deck-burger" id="deck-burger-gate">
              <div></div>
              <div></div>
            </div>
          </div>
        </nav>

        <div class="right-parallax-indicator">
          <span class="scrub-text">SCROLL SCRUB</span>
          <div class="progress-tube-bar">
            <div class="progress-tube-fill" id="indicator-tube-fill"></div>
          </div>
        </div>

        <div style="width:100%; height:100%; position:relative; transform-style:preserve-3d;">

DECK;

// Cards
$cards = [];

$cards_data = [
    [
        'numberCode' => 'IFM.001 // FRESH_CATCH',
        'color' => 'rgb(15, 76, 129)',
        'title' => 'Fresh Catch',
        'description' => 'Our platform begins with premium seafood. We highlight freshness, sourcing, and visual quality so restaurants can present the story behind what they serve.',
        'subtitle' => 'Ocean First',
        'image' => '/assets/seafood_banner_bg_1780142350689-Xpud7jgV.png',
    ],
    [
        'numberCode' => 'IFM.002 // SEAFOOD_CARE',
        'color' => 'rgb(13, 148, 136)',
        'title' => 'Seafood Care',
        'description' => 'Cold-chain handling, texture protection, and product care all shape how seafood arrives in the kitchen and how confidently restaurants can share it with their guests.',
        'subtitle' => 'Handled Well',
        'image' => '/assets/Salmon-Sockeye-LY__RHmg.png',
    ],
    [
        'numberCode' => 'IFM.003 // RECIPE_SHARE',
        'color' => 'rgb(234, 122, 47)',
        'title' => 'Recipe Share',
        'description' => 'International Fish Market is not here to write recipes for them. We give restaurants a place to share their own seafood dishes, ideas, plating, and kitchen stories with a wider audience.',
        'subtitle' => 'Restaurant Voices',
        'image' => '/assets/recipe1-0-t-hNS_.png',
    ],
    [
        'numberCode' => 'IFM.004 // TABLE_STORY',
        'color' => 'rgb(53, 92, 125)',
        'title' => 'Table Story',
        'description' => 'From seafood selection to restaurant creativity, the final story belongs at the table. International Fish Market closes the loop by connecting product, people, recipes, and presentation in one place.',
        'subtitle' => 'Closing The Loop',
        'image' => '/assets/recipe3-u_vq5WAz.png',
    ],
];

foreach ($cards_data as $index => $card) {
    $stageNum = $index + 1;
    $words = explode(' ', $card['title']);
    $wrapped = [];
    foreach ($words as $w) {
        $wrapped[] = '          <span>' . htmlspecialchars($w) . '</span>';
    }
    $wordsHtml = implode("\n", $wrapped);
    
    $cards[] = <<<CARD
          <div id="card-node-{$stageNum}" class="stack-card" style="background-color: {$card['color']};">
            <div class="card-info">
              <div>
                <span style="font-family:'DM Mono',monospace; font-size:10px; opacity:0.4; display:block; letter-spacing:1px; text-transform:uppercase;">
                  {$card['numberCode']}
                </span>
                <h2>
{$wordsHtml}
                </h2>
              </div>
              <div class="card-meta">
                <p>{$card['description']}</p>
                <p style="margin-top:16px; font-weight:bold; opacity:0.6; text-transform:uppercase;">
                  {$card['subtitle']} // ACTIVE
                </p>
              </div>
            </div>
            <div class="card-media">
              <img src="{$card['image']}" alt="{$card['title']}">
            </div>
          </div>
CARD;
}

$deckEnd2 = <<<'DECK2'
        </div>

        <footer class="deck-footer">
          <div style="max-width:320px; text-align:left;">
            <p id="dynamic-footer-log" style="font-size:10px; font-family:'DM Mono',monospace; line-height:1.6; opacity:0.6; font-style:italic;">
              * AUTOMATED NEURAL ASSEMBLY PROCESS INITIATED.
            </p>
          </div>
          <div style="display:flex; flex-direction:column; align-items:flex-end; gap:12px;">
            <div style="display:flex; gap:12px;">
              <button class="deck-footer-btn" id="deck-btn-prev">&larr;</button>
              <button class="deck-footer-btn" id="deck-btn-next" style="background-color:#000; color:#fff;">&rarr;</button>
            </div>
            <span id="deck-next-preview-label" style="font-size:10px; font-family:'DM Mono',monospace; letter-spacing:0.2em; text-transform:uppercase; font-weight:bold;">
              NEXT: SEAFOOD CARE
            </span>
          </div>
        </footer>
      </div>

      <div class="floating-capsule-nav" id="capsule-nav-bar">
DECK2;

$capsuleItems = [];
foreach ($cards_data as $index => $card) {
    $stageNum = $index + 1;
    $capsuleItems[] = <<<CAP
        <button class="capsule-item" data-target-stage="{$stageNum}">
          <span class="capsule-dot" style="background-color: {$card['color']};"></span>
          <span>0{$stageNum}</span>
        </button>
CAP;
}
$capsuleHtml = implode("\n", $capsuleItems);

$deckEnd3 = <<<'DECK3'
      </div>

      <section id="stage-panel-outro">
        <div class="outro-lines-bg"></div>
        <div class="outro-glow"></div>

        <div style="z-index:10; display:flex; justify-content:space-between; font-family:'DM Mono',monospace; font-size:12px; color:rgba(255,255,255,0.4);">
          <span>04 // 04 COMPLETED</span>
          <span>GRADIENT OPT LEVEL: 100%</span>
        </div>

        <div style="z-index:10; text-align:center; margin:auto; display:flex; flex-direction:column; align-items:center;">
          <p style="font-family:'DM Mono',monospace; font-size:12px; color:#f97316; letter-spacing:0.4em; font-weight:600; margin-bottom:16px;">SEQUENCE FINALIZED</p>
          <h1 style="font-size:clamp(4rem, 9vw, 9rem); font-weight:900; font-style:italic; text-transform:uppercase; color:#fff; line-height:0.85; letter-spacing:-0.03em;">
            <span>Loop</span><br>
            <span style="color:#f97316;">Complete</span>
          </h1>
          <button class="replay-action-trigger" id="btn-trigger-replay">
            <span>Replay Transition</span>
          </button>
        </div>

        <div style="z-index:10; display:flex; justify-content:space-between; align-items:center; font-family:'DM Mono',monospace; font-size:10px; color:rgba(255,255,255,0.3); border-top:1px solid rgba(255,255,255,0.05); padding-top:16px;">
          <div>DESIGN INSPIRED BY <span style="color:rgba(255,255,255,0.6);">Codegrid</span></div>
          <div>PERSISTENT CORE LABS &copy; 2026</div>
          <div>UTC TIME: 28:46 // GMT+0</div>
        </div>
      </section>

    </div>
DECK3;

// Insert deck HTML after the first part
$insert = $deckEnd . implode("\n", $cards) . "\n" . $deckEnd2 . $capsuleHtml . "\n" . $deckEnd3;
array_splice($lines, 340, 0, $insert);

file_put_contents($file, implode('', $lines));
echo "Done. Deck inserted.";
