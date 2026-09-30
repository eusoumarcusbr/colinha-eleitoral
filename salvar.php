<?php
/*
 * Registro anônimo das colinhas geradas.
 * Guarda só números, nomes digitados de candidatos e a vaga do Pedro no Senado.
 * Não guarda IP, user agent, cookie nem qualquer dado da pessoa.
 * Uma linha JSON por colinha em dados/colinhas-AAAA-MM.jsonl
 */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$bruto = file_get_contents('php://input', false, null, 0, 4096);
if ($bruto === false || strlen($bruto) < 10) { http_response_code(400); exit; }

$d = json_decode($bruto, true);
if (!is_array($d)) { http_response_code(400); exit; }

$tamanhos = ['df' => 4, 'de' => 5, 's1' => 3, 's2' => 3, 'gov' => 2, 'pres' => 2];
$vaga = isset($d['vaga_pedro']) ? (int)$d['vaga_pedro'] : 0;
if ($vaga !== 1 && $vaga !== 2) { http_response_code(400); exit; }

$votos = [];
$nomes = [];
foreach ($tamanhos as $id => $n) {
    $v = isset($d['votos'][$id]) ? preg_replace('/\D/', '', (string)$d['votos'][$id]) : '';
    $votos[$id] = substr($v, 0, $n);
    if (isset($d['nomes'][$id]) && is_string($d['nomes'][$id])) {
        $nome = strip_tags($d['nomes'][$id]);
        $nome = preg_replace('/[\x00-\x1F\x7F]/u', '', $nome);
        $nome = trim(mb_substr($nome, 0, 40, 'UTF-8'));
        if ($nome !== '') $nomes[$id] = $nome;
    }
}
/* O Pedro precisa estar na vaga informada e não pode estar nas duas */
if ($votos['s' . $vaga] !== '400') { http_response_code(400); exit; }
if ($votos['s' . (3 - $vaga)] === '400') { http_response_code(400); exit; }

$acoes = ['baixar', 'compartilhar', 'imprimir'];
$acao = (isset($d['acao']) && in_array($d['acao'], $acoes, true)) ? $d['acao'] : 'outro';
$tela = (isset($d['tela']) && $d['tela'] === 'toque') ? 'toque' : 'mouse';

date_default_timezone_set('America/Cuiaba');
$linha = json_encode([
    'data'       => date('c'),
    'acao'       => $acao,
    'tela'       => $tela,
    'vaga_pedro' => $vaga,
    'votos'      => $votos,
    'nomes'      => (object)$nomes,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

$pasta = __DIR__ . '/dados';
if (!is_dir($pasta)) { @mkdir($pasta, 0750, true); }
$arquivo = $pasta . '/colinhas-' . date('Y-m') . '.jsonl';

/* Trava de segurança: não deixa um único arquivo passar de 200 MB */
if (is_file($arquivo) && filesize($arquivo) > 200 * 1024 * 1024) { http_response_code(507); exit; }

if (@file_put_contents($arquivo, $linha, FILE_APPEND | LOCK_EX) === false) { http_response_code(500); exit; }
http_response_code(204);
