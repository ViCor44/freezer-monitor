# Freezer Monitor

Aplicacao PHP para monitorizacao de temperatura de congeladores com alertas e painel administrativo.

## Installation

1. Colocar o projeto em `c:\xampp\htdocs\freezer-monitor`.
2. Importar `database/init.sql` no MySQL (phpMyAdmin).
3. Em bases de dados existentes, executar tambem `database/update_add_calibration_factor.sql`.
4. Configurar o ficheiro `.env` com as credenciais da base de dados.
5. Iniciar Apache e MySQL no XAMPP.

## URLs

- Entrada recomendada: `http://localhost/freezer-monitor`
- Entrada direta: `http://localhost/freezer-monitor/public`

## Usage

Depois de iniciar sessao, o sistema permite:

- Ver estado dos dispositivos e leitura atual
- Consultar historico e graficos
- Gerir utilizadores, dispositivos e alertas (admin)
