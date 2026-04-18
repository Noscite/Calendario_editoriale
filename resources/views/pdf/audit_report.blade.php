<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9pt; color: #1A2E35; background: #fff; }

  .page-header {
    background: #1A2E35;
    color: #fff;
    padding: 20px 24px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 18px;
  }
  .page-header h1 { font-size: 16pt; font-weight: 700; color: #3DAFA8; }
  .page-header .meta { font-size: 8pt; color: #9CA3AF; margin-top: 4px; }
  .page-header .brand-name { font-size: 11pt; color: #fff; font-weight: 600; }
  .page-header .score-badge {
    background: #3DAFA8;
    color: #fff;
    border-radius: 50%;
    width: 54px;
    height: 54px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    text-align: center;
    font-size: 18pt;
    font-weight: 700;
  }

  .section {
    margin-bottom: 14px;
    padding: 12px 14px;
    border: 1px solid #E5E7EB;
    border-radius: 8px;
    page-break-inside: avoid;
  }
  .section-title {
    font-size: 10.5pt;
    font-weight: 700;
    color: #1A2E35;
    margin-bottom: 10px;
    padding-bottom: 6px;
    border-bottom: 2px solid #3DAFA8;
  }

  .score-grid { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
  .score-grid td { padding: 6px 8px; text-align: center; }
  .score-label { font-size: 7.5pt; color: #6B7280; margin-bottom: 3px; }
  .score-value { font-size: 18pt; font-weight: 700; }
  .score-bar-bg { height: 4px; background: #E5E7EB; border-radius: 2px; margin-top: 4px; }
  .score-bar    { height: 4px; border-radius: 2px; }

  .executive-text { font-size: 8.5pt; line-height: 1.65; color: #374151; }

  .issue-row { display: flex; gap: 8px; margin-bottom: 6px; align-items: flex-start; }
  .issue-badge {
    font-size: 6.5pt; font-weight: 700; text-transform: uppercase;
    padding: 2px 6px; border-radius: 4px; flex-shrink: 0; margin-top: 1px;
  }
  .badge-critical  { background: #FEE2E2; color: #DC2626; }
  .badge-warning   { background: #FEF9C3; color: #D97706; }
  .badge-info      { background: #DBEAFE; color: #2563EB; }

  .rec-row { margin-bottom: 6px; padding: 7px 10px; background: #F9FAFB; border-radius: 5px; }
  .rec-meta { font-size: 7pt; color: #6B7280; margin-bottom: 3px; }
  .rec-action { font-size: 8.5pt; color: #1A2E35; }
  .tag {
    display: inline-block;
    font-size: 6.5pt; font-weight: 700; text-transform: uppercase;
    padding: 1px 5px; border-radius: 3px; margin-right: 4px;
  }
  .tag-basso { background: #D1FAE5; color: #059669; }
  .tag-medio { background: #FEF3C7; color: #D97706; }
  .tag-alto  { background: #FEE2E2; color: #DC2626; }

  .footer {
    margin-top: 16px;
    text-align: center;
    font-size: 7pt;
    color: #9CA3AF;
    border-top: 1px solid #E5E7EB;
    padding-top: 8px;
  }
</style>
</head>
<body>

{{-- Header --}}
<div class="page-header">
  <div>
    <div class="brand-name">{{ $brandName ?? 'Brand' }}</div>
    <h1>Digital Audit Report</h1>
    <div class="meta">
      {{ $websiteUrl ?? '' }} &nbsp;|&nbsp;
      Generato il {{ now()->format('d/m/Y H:i') }}
    </div>
  </div>
  <div class="score-badge">
    {{ $scores['overall'] ?? '—' }}
  </div>
</div>

@php
$metodologie = [
    'neuromarketing' => [
        'fonte'  => 'Claude Vision AI + analisi testo DOM renderizzato',
        'metodo' => 'Screenshot above-fold analizzato da AI per gerarchia visiva, CTA e social proof. Contestualizzato per settore.',
    ],
    'seo_geo' => [
        'fonte'  => 'Analisi DOM renderizzato + Google PageSpeed Insights API',
        'metodo' => 'Verifica meta tag, struttura heading, schema.org, sitemap. Core Web Vitals da Google (LCP, CLS, FID).',
    ],
    'gdpr' => [
        'fonte'  => 'Playwright (intercettazione rete reale) + analisi testo pagina',
        'metodo' => 'Intercettazione chiamate di rete prima del consenso. Rilevamento visivo banner cookie. Ricerca link privacy nel DOM renderizzato.',
    ],
    'accessibility' => [
        'fonte'  => 'axe-core WCAG 2.1 AA (standard W3C)',
        'metodo' => 'Suite di test automatici su DOM renderizzato. 50+ check: alt tag, struttura heading, contrasto, ARIA, focus.',
    ],
    'performance' => [
        'fonte'  => 'Google PageSpeed Insights API (dati Lighthouse)',
        'metodo' => 'Simulazione caricamento su connessione mobile 4G lenta. Core Web Vitals ufficiali Google.',
    ],
];

$metodoBox = function(string $key) use ($metodologie): string {
    $m = $metodologie[$key] ?? null;
    if (!$m) return '';
    return '<div style="background:#F0FAFA;border-left:2px solid #3DAFA8;border-radius:4px;padding:5px 10px;margin-bottom:10px;margin-top:-4px;">'
        . '<span style="font-size:7pt;font-weight:700;color:#3DAFA8;text-transform:uppercase;letter-spacing:0.3px;">Fonte: </span>'
        . '<span style="font-size:7pt;color:#4B5563;">' . $m['fonte'] . '</span><br>'
        . '<span style="font-size:7pt;color:#6B7280;">' . $m['metodo'] . '</span>'
        . '</div>';
};
@endphp

{{-- Score panoramica --}}
<div class="section">
  <div class="section-title">Score per categoria</div>
  {!! $metodoBox('seo_geo') !!}
  @php
    $scoreMap = [
      'Neuromarketing' => ['key' => 'neuromarketing',   'color' => null],
      'SEO & GEO'      => ['key' => 'seo_geo',          'color' => null],
      'GDPR'           => ['key' => 'gdpr',             'color' => null],
      'Contenuto'      => ['key' => 'social_coherence', 'color' => 'disabled'],
      'Accessibilità'  => ['key' => 'accessibility',    'color' => null],
      'Performance'    => ['key' => 'performance',      'color' => null],
    ];
    $colorFn = function($s) {
      if ($s === null) return '#9CA3AF';
      if ($s >= 80) return '#3DAFA8';
      if ($s >= 60) return '#F59E0B';
      if ($s >= 40) return '#D4724A';
      return '#EF4444';
    };
  @endphp
  <table class="score-grid">
    <tr>
      @foreach($scoreMap as $label => $info)
        @php
          $s        = $scores[$info['key']] ?? null;
          $disabled = ($info['color'] ?? null) === 'disabled';
          $c        = $disabled ? '#9CA3AF' : $colorFn($s);
        @endphp
        <td>
          <div class="score-label">{{ $label }}</div>
          @if($disabled)
            <div class="score-value" style="color: #9CA3AF; font-size: 14pt;">—</div>
            <div style="font-size: 7pt; color: #9CA3AF; margin-top: 4px;">In sviluppo</div>
          @else
            <div class="score-value" style="color: {{ $c }}">{{ $s ?? '—' }}</div>
            <div class="score-bar-bg">
              <div class="score-bar" style="width: {{ $s ?? 0 }}%; background: {{ $c }};"></div>
            </div>
          @endif
        </td>
      @endforeach
    </tr>
  </table>
</div>

{{-- Executive Summary --}}
@if(!empty($executiveSummary))
<div class="section">
  <div class="section-title">Executive Summary</div>
  {!! $metodoBox('neuromarketing') !!}
  <p class="executive-text">{{ $executiveSummary }}</p>
</div>
@endif

{{-- Neuromarketing Visivo --}}
@php $vision = $analyzerResults['neuromarketing']['vision'] ?? null; @endphp
@if($vision && empty($vision['error']) && isset($vision['above_fold_verdict']))
<div class="section">
  <div class="section-title">Neuromarketing Visivo <span style="font-size: 8pt; color: #3DAFA8; font-weight: normal;">• Vision AI</span></div>
  {!! $metodoBox('neuromarketing') !!}

  {{-- Verdict --}}
  <div style="background: #E8F7F6; border-left: 3px solid #3DAFA8; border-radius: 6px; padding: 10px 12px; margin-bottom: 10px;">
    <p style="font-size: 9pt; color: #1A2E35; font-weight: 600;">{{ $vision['above_fold_verdict'] }}</p>
  </div>

  {{-- Sub-score grid --}}
  <table style="width: 100%; border-collapse: collapse; margin-bottom: 8px;">
    <tr>
      @php
        $subScores = [
          ['Gerarchia', 'hierarchy_score'],
          ['CTA', 'cta_score'],
          ['Val. Prop.', 'value_prop_score'],
          ['Soc. Proof', 'social_proof_score'],
          ['Coerenza', 'brand_coherence_score'],
          ['Mobile UX', 'mobile_score'],
        ];
      @endphp
      @foreach($subScores as [$lbl, $key])
        @if(isset($vision[$key]))
          @php $s = $vision[$key]; $c = $colorFn($s); @endphp
          <td style="width: 16.6%; text-align: center; padding: 4px;">
            <div style="font-size: 7pt; color: #6B7280; margin-bottom: 3px;">{{ $lbl }}</div>
            <div style="font-size: 15pt; font-weight: 700; color: {{ $c }};">{{ $s }}</div>
            <div style="height: 3px; background: #E5E7EB; border-radius: 2px; margin-top: 3px;">
              <div style="width: {{ $s }}%; height: 3px; background: {{ $c }}; border-radius: 2px;"></div>
            </div>
          </td>
        @endif
      @endforeach
    </tr>
  </table>

  {{-- Miglioramenti critici --}}
  @if(!empty($vision['critical_improvements']))
  <div style="margin-top: 6px;">
    <p style="font-size: 7.5pt; font-weight: 700; color: #6B7280; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 5px;">Miglioramenti critici</p>
    @foreach($vision['critical_improvements'] as $imp)
    <div style="background: #FDF0EB; border-left: 3px solid #D4724A; border-radius: 4px; padding: 7px 10px; margin-bottom: 4px;">
      <div style="font-size: 8pt; font-weight: 700; color: #D4724A;">{{ $imp['element'] ?? '' }}</div>
      <div style="font-size: 7.5pt; color: #6B7280; margin-top: 2px;"><strong>Ora:</strong> {{ $imp['current'] ?? '' }}</div>
      <div style="font-size: 7.5pt; color: #6B7280;"><strong>Suggerito:</strong> {{ $imp['suggested'] ?? '' }}</div>
      @if(!empty($imp['impact']))
      <div style="font-size: 7pt; color: #9CA3AF; font-style: italic; margin-top: 2px;">{{ $imp['impact'] }}</div>
      @endif
    </div>
    @endforeach
  </div>
  @endif

  {{-- Note settore --}}
  @if(!empty($vision['sector_specific_notes']))
  <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 5px; padding: 8px 10px; margin-top: 6px;">
    <p style="font-size: 7pt; font-weight: 700; color: #6B7280; text-transform: uppercase; margin-bottom: 3px;">Benchmark settore</p>
    <p style="font-size: 8pt; color: #1A2E35;">{{ $vision['sector_specific_notes'] }}</p>
  </div>
  @endif
</div>
@endif

{{-- Problemi critici --}}
@if(!empty($criticalIssues))
<div class="section">
  <div class="section-title">Problemi critici</div>
  {!! $metodoBox('gdpr') !!}
  {!! $metodoBox('accessibility') !!}
  @foreach($criticalIssues as $issue)
    <div class="issue-row">
      <span class="issue-badge badge-critical">{{ $issue['area'] ?? 'N/D' }}</span>
      <div>
        <div style="font-size: 8.5pt; color: #1A2E35;">{{ $issue['message'] ?? '' }}</div>
        @if(!empty($issue['impact']))
          <div style="font-size: 7.5pt; color: #6B7280; font-style: italic; margin-top: 2px;">{{ $issue['impact'] }}</div>
        @endif
      </div>
    </div>
  @endforeach
</div>
@endif

{{-- Raccomandazioni --}}
@if(!empty($recommendations))
<div class="section">
  <div class="section-title">Piano d'azione</div>
  <div style="background:#FDF0EB;border-left:2px solid #D4724A;border-radius:4px;padding:5px 10px;margin-bottom:10px;margin-top:-4px;">
    <span style="font-size:7pt;font-weight:700;color:#D4724A;text-transform:uppercase;letter-spacing:0.3px;">Metodologia: </span>
    <span style="font-size:7pt;color:#4B5563;">Claude Sonnet AI con contesto settoriale e vincoli deontologici</span><br>
    <span style="font-size:7pt;color:#6B7280;">Le raccomandazioni rispettano le normative professionali del settore dichiarato.</span>
  </div>
  @foreach($recommendations as $i => $rec)
    <div class="rec-row">
      <div class="rec-meta">
        <strong style="color: #3DAFA8;">#{{ $rec['priority'] ?? ($i + 1) }}</strong>
        &nbsp;
        <span style="font-weight: 700; text-transform: uppercase;">{{ $rec['area'] ?? '' }}</span>
        &nbsp;
        @if(!empty($rec['effort']))
          <span class="tag tag-{{ $rec['effort'] }}">{{ $rec['effort'] }}</span>
        @endif
        @if(!empty($rec['impact']))
          <span class="tag" style="background: #DBEAFE; color: #2563EB;">impatto {{ $rec['impact'] }}</span>
        @endif
      </div>
      <div class="rec-action">{{ $rec['action'] ?? '' }}</div>
    </div>
  @endforeach
</div>
@endif

<div class="footer">
  Report generato da Kalendarium &bull; Digital Audit AI &bull; Powered by Claude Vision
</div>

</body>
</html>
