#!/usr/bin/env python3
"""
Gera colinha/candidatos.json e colinha/fotos/ a partir dos dados abertos do TSE.

Entradas (baixadas de dadosabertos.tse.jus.br/dataset/candidatos-2026):
  consulta_cand_2026.zip        base de candidatos (todas as UFs)
  foto_cand2026_MT_div.zip      fotos de MT
  foto_cand2026_BR_div.zip      fotos de Presidente

Regras:
  - Cargos de MT: governador, senador, dep. federal, dep. estadual. Presidente: BR.
  - Só entram candidaturas que não estão INAPTAS.
  - Foto só para candidatos de partidos da coligação do Pedro Taques (lida da
    composição da coligação na própria base do TSE). Os demais: só nome e partido.
  - Legendas (2 dígitos para deputado) vêm dos partidos presentes na base.

Uso: python3 gerar_candidatos.py PASTA_DOS_ZIPS PASTA_COLINHA
"""
import csv, io, json, re, sys, unicodedata, zipfile
from datetime import datetime
from pathlib import Path
from PIL import Image

ORIG, DEST = Path(sys.argv[1]), Path(sys.argv[2])
CARGOS = {'1': 'pres', '3': 'gov', '5': 'sen', '6': 'df', '7': 'de'}
PEDRO_NUM = '400'


def norm(s):
    s = re.sub(r'^\s*\d+\s*-\s*', '', s or '')  # '13-PT' -> 'PT'
    s = unicodedata.normalize('NFKD', s).encode('ascii', 'ignore').decode()
    return re.sub(r'[^A-Z0-9]', '', s.upper())


def ler_csv():
    z = zipfile.ZipFile(ORIG / 'consulta_cand_2026.zip')
    nomes = [n for n in z.namelist() if n.lower().endswith('.csv')]
    alvo = [n for n in nomes if n.upper().endswith(('_MT.CSV', '_BR.CSV'))] or \
           [n for n in nomes if 'BRASIL' in n.upper()]
    for n in alvo:
        with z.open(n) as f:
            for row in csv.DictReader(io.TextIOWrapper(f, encoding='latin-1'), delimiter=';'):
                yield row


def partidos_da_coligacao(comp):
    """'PDT / PSB / Federação X(PT/PC do B/PV) / ...' -> {'PDT','PSB','PT','PCDOB','PV',...}"""
    siglas = set()
    for dentro in re.findall(r'\(([^)]*)\)', comp):
        siglas.update(norm(p) for p in dentro.split('/'))
    fora = re.sub(r'\([^)]*\)', '', comp)
    for p in fora.split('/'):
        p = p.strip()
        if p and not p.lower().startswith('federa'):
            siglas.add(norm(p))
    siglas.discard('')
    return siglas


def main():
    linhas = []
    for r in ler_csv():
        cargo = CARGOS.get(r.get('CD_CARGO', '').strip())
        uf = r.get('SG_UF', '').strip()
        if not cargo or (cargo == 'pres') != (uf == 'BR') or (cargo != 'pres' and uf != 'MT'):
            continue
        linhas.append((cargo, r))

    pedro = [r for c, r in linhas if c == 'sen' and r['NR_CANDIDATO'].strip() == PEDRO_NUM]
    if not pedro:
        sys.exit('Não achei o 400 para Senado em MT na base. Confira o arquivo.')
    comp = pedro[0].get('DS_COMPOSICAO_COLIGACAO') or pedro[0].get('DS_COMPOSICAO_FEDERACAO') or ''
    partido_pedro = norm(pedro[0]['SG_PARTIDO'])
    nome_colig = pedro[0].get('NM_COLIGACAO', '').strip()
    coligacao = partidos_da_coligacao(comp) | {partido_pedro}
    print('Coligação:', nome_colig)
    print('Partidos da coligação:', sorted(coligacao))

    fotos = {}
    for zf in ('foto_cand2026_MT_div.zip', 'foto_cand2026_BR_div.zip'):
        p = ORIG / zf
        if not p.exists():
            print('Aviso: sem', zf)
            continue
        z = zipfile.ZipFile(p)
        for n in z.namelist():
            m = re.search(r'(\d{9,})', Path(n).name)
            if m:
                fotos[m.group(1)] = (z, n)

    pasta_fotos = DEST / 'fotos'
    pasta_fotos.mkdir(parents=True, exist_ok=True)
    saida = {'cargos': {'df': {}, 'de': {}, 'sen': {}, 'gov': {}, 'pres': {}}, 'legendas': {}}
    n_fotos = 0
    for cargo, r in linhas:
        num = r['NR_CANDIDATO'].strip()
        sig = r['SG_PARTIDO'].strip()
        saida['legendas'][r['NR_PARTIDO'].strip()] = sig
        if 'INAPTO' in (r.get('DS_SITUACAO_CANDIDATURA') or '').upper():
            continue
        item = {'n': r['NM_URNA_CANDIDATO'].strip(), 'p': sig}
        sq_atual = r['SQ_CANDIDATO'].strip()
        antigo = saida['cargos'][cargo].get(num)
        if antigo and antigo.get('_sq', '') > sq_atual:
            continue  # substituição: fica o registro mais recente
        item['_sq'] = sq_atual
        if cargo in ('sen', 'gov'):
            aliado = r.get('NM_COLIGACAO', '').strip() == nome_colig
        elif cargo == 'pres':
            aliado = partido_pedro in partidos_da_coligacao(r.get('DS_COMPOSICAO_COLIGACAO', ''))
        else:
            aliado = norm(sig) in coligacao
        if aliado and num != PEDRO_NUM:
            sq = r['SQ_CANDIDATO'].strip()
            if sq in fotos:
                z, n = fotos[sq]
                with z.open(n) as f:
                    im = Image.open(f).convert('RGB')
                im.thumbnail((180, 240), Image.LANCZOS)
                im.save(pasta_fotos / f'{cargo}-{num}.jpg', quality=82, optimize=True, progressive=True)
                item['f'] = 1
                n_fotos += 1
        saida['cargos'][cargo][num] = item

    for lista in saida['cargos'].values():
        for it in lista.values():
            it.pop('_sq', None)
    saida['atualizado'] = datetime.now().strftime('%Y-%m-%d')
    saida['fonte'] = 'TSE, Portal de Dados Abertos, candidatos 2026'
    (DEST / 'candidatos.json').write_text(json.dumps(saida, ensure_ascii=False, separators=(',', ':')), encoding='utf-8')
    tot = {k: len(v) for k, v in saida['cargos'].items()}
    print('Candidatos por cargo:', tot, '| fotos:', n_fotos, '| legendas:', len(saida['legendas']))


if __name__ == '__main__':
    main()
