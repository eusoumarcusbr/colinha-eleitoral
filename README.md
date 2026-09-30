# Colinha eleitoral · Pedro Taques 400

App para o eleitor montar a colinha na ordem da urna (Eleições 2026, MT) e salvar, compartilhar ou imprimir.
Publicado em https://voudetaques.com.br/colinha/

## Arquivos

- `index.html`: o app (HTML, CSS e JS num arquivo só).
- `candidatos.json` e `fotos/`: candidatos de MT e Presidente, gerados dos dados abertos do TSE. Foto só para candidatos da coligação.
- `salvar.php`: registro anônimo das colinhas em `dados/`.
- `resumo.php`: painel dos registros. Precisa de `config.local.php` com `<?php const CHAVE = '...';`.
- `ferramentas/gerar_candidatos.py`: gera `candidatos.json` e `fotos/` a partir dos zips do TSE.

## Atualizar a base do TSE

Baixe em dadosabertos.tse.jus.br (dataset candidatos-2026) os arquivos `consulta_cand_2026.zip`, `foto_cand2026_MT_div.zip` e `foto_cand2026_BR_div.zip` numa pasta e rode:

    python3 ferramentas/gerar_candidatos.py PASTA_DOS_ZIPS .

## Publicar

Suba tudo, mais `config.local.php`, para `public_html/colinha`. A pasta `dados/` precisa de permissão de escrita para o PHP.
