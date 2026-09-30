<?php
/*
 * Resumo das colinhas registradas.
 *   resumo.php?chave=SUA_CHAVE              painel em HTML
 *   resumo.php?chave=SUA_CHAVE&formato=json resumo em JSON
 *   resumo.php?chave=SUA_CHAVE&formato=csv  base completa em CSV (para análise)
 * A chave de acesso fica em config.local.php, que não vai para o git.
 */
/* A chave fica em config.local.php (fora do git): <?php const CHAVE = 'sua-chave'; */
if (is_file(__DIR__ . '/config.local.php')) require __DIR__ . '/config.local.php';
if (!defined('CHAVE') || CHAVE === '') { http_response_code(404); exit; }

if (!isset($_GET['chave']) || !hash_equals(CHAVE, (string)$_GET['chave'])) { http_response_code(404); exit; }
header('Cache-Control: no-store');

$cargos = ['df' => 'Dep. federal', 'de' => 'Dep. estadual', 's1' => 'Senado 1º', 's2' => 'Senado 2º', 'gov' => 'Governo', 'pres' => 'Presidente'];
$linhas = [];
foreach (glob(__DIR__ . '/dados/colinhas-*.jsonl') ?: [] as $f) {
    $h = fopen($f, 'r');
    while (($l = fgets($h)) !== false) { $j = json_decode($l, true); if ($j) $linhas[] = $j; }
    fclose($h);
}
$formato = isset($_GET['formato']) ? $_GET['formato'] : 'html';

if ($formato === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="colinhas.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $cab = ['data', 'acao', 'tela', 'vaga_pedro'];
    foreach ($cargos as $id => $n) { $cab[] = $id; $cab[] = $id . '_nome'; }
    fputcsv($out, $cab, ';');
    foreach ($linhas as $r) {
        $row = [$r['data'], $r['acao'], $r['tela'], $r['vaga_pedro']];
        foreach ($cargos as $id => $n) { $row[] = $r['votos'][$id] ?? ''; $row[] = $r['nomes'][$id] ?? ''; }
        fputcsv($out, $row, ';');
    }
    exit;
}

$res = ['total' => count($linhas), 'vaga_pedro' => ['1' => 0, '2' => 0], 'acao' => [], 'tela' => [], 'por_dia' => [], 'numeros' => []];
foreach ($cargos as $id => $n) $res['numeros'][$id] = [];
foreach ($linhas as $r) {
    $res['vaga_pedro'][(string)$r['vaga_pedro']]++;
    $res['acao'][$r['acao']] = ($res['acao'][$r['acao']] ?? 0) + 1;
    $res['tela'][$r['tela']] = ($res['tela'][$r['tela']] ?? 0) + 1;
    $dia = substr($r['data'], 0, 10);
    $res['por_dia'][$dia] = ($res['por_dia'][$dia] ?? 0) + 1;
    foreach ($cargos as $id => $n) {
        $v = $r['votos'][$id] ?? '';
        if ($v === '') $v = '(em branco)';
        $res['numeros'][$id][$v] = ($res['numeros'][$id][$v] ?? 0) + 1;
    }
}
foreach ($res['numeros'] as $id => &$lista) { arsort($lista); $lista = array_slice($lista, 0, 15, true); }
unset($lista);
ksort($res['por_dia']);

if ($formato === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$t = max(1, $res['total']);
?><!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Resumo das colinhas</title>
<style>
body{font-family:Barlow,"Helvetica Neue",Arial,sans-serif;color:#383838;margin:0;background:#F5F5F3}
header{background:#2B1574;color:#fff;padding:20px}header h1{margin:0;font-size:1.4rem;text-transform:uppercase}
main{max-width:1000px;margin:0 auto;padding:20px;display:grid;gap:16px;grid-template-columns:repeat(auto-fill,minmax(280px,1fr))}
.card{background:#fff;border-radius:4px;padding:16px;border-top:6px solid #FFC000}
.card h2{margin:0 0 10px;font-size:1rem;text-transform:uppercase;color:#2B1574}
.big{font-size:2.4rem;font-weight:800;color:#2B1574}
table{width:100%;border-collapse:collapse;font-size:.9rem}td{padding:4px 0;border-bottom:1px solid #eee}td:last-child{text-align:right;font-weight:800}
a{color:#F29500;font-weight:800}
</style></head><body>
<header><h1>Colinhas geradas</h1></header>
<main>
<div class="card"><h2>Total</h2><div class="big"><?= $res['total'] ?></div>
<p><a href="?chave=<?= e(CHAVE) ?>&amp;formato=csv">Baixar base em CSV</a></p></div>
<div class="card"><h2>Pedro no Senado</h2><table>
<tr><td>1º voto</td><td><?= $res['vaga_pedro']['1'] ?> (<?= round($res['vaga_pedro']['1'] * 100 / $t) ?>%)</td></tr>
<tr><td>2º voto</td><td><?= $res['vaga_pedro']['2'] ?> (<?= round($res['vaga_pedro']['2'] * 100 / $t) ?>%)</td></tr></table></div>
<div class="card"><h2>Ação e aparelho</h2><table>
<?php foreach ($res['acao'] as $k => $v): ?><tr><td><?= e($k) ?></td><td><?= $v ?></td></tr><?php endforeach; ?>
<?php foreach ($res['tela'] as $k => $v): ?><tr><td><?= $k === 'toque' ? 'celular/tablet' : 'computador' ?></td><td><?= $v ?></td></tr><?php endforeach; ?>
</table></div>
<?php foreach ($cargos as $id => $nome): ?>
<div class="card"><h2><?= e($nome) ?></h2><table>
<?php foreach ($res['numeros'][$id] as $num => $q): ?><tr><td><?= e($num) ?></td><td><?= $q ?></td></tr><?php endforeach; ?>
</table></div>
<?php endforeach; ?>
<div class="card"><h2>Por dia</h2><table>
<?php foreach ($res['por_dia'] as $d => $q): ?><tr><td><?= e($d) ?></td><td><?= $q ?></td></tr><?php endforeach; ?>
</table></div>
</main></body></html>
